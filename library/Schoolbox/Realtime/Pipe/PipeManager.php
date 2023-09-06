<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Pipe;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\QueueingException;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Alaress\WorkerCrew\Transaction\Transaction;
use ErrorException;
use Exception;
use InvalidArgumentException;
use Predis\CommunicationException;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\ProcessException;
use Schoolbox\Realtime\SessionException;
use Schoolbox\Realtime\Subscription\ISubscriptionManager;

/**
 * Multi-instance aware pipe manager.
 */
class PipeManager implements MessageComponentInterface, TaskResponseHandlerInterface, Async\EventListener
{
    public const REDIS_SESSION_PREFIX_BASE = 'PHPREDIS_SESSION:';

    private const INACTIVE_TIMEOUT     = 300; // 5 minutes

    public const PIPE_METHOD_ACKNOWLEDGE = 'ack';
    public const PIPE_METHOD_ALERT       = 'alert';
    public const PIPE_METHOD_FETCH       = 'fetch';
    public const PIPE_METHOD_PUSH        = 'push';
    public const PIPE_METHOD_SUBSCRIBE   = 'subscribe';
    public const PIPE_METHOD_WAIT        = 'wait';

    /**
     * On the matter of File Descriptors: one or more file descriptors are opened by this process every time...
     *  - guzzle creates a HTTP responses (in-memory tempfile)
     *  - a websocket connects (duplex socket)
     *  - a worker is spawned (triplex socket)
     *
     * The operating system places limits on the number of file descriptors a process may have open at any one time,
     * and will mercilessly punish processes that attempt to exceed their limit. In the interests of stability, this
     * is undesirable. This limit buffer is to mitigate any possible race condition between sockets opening and
     * others closing.
     */
    private const FD_LIMIT_BUFFER = 16;

    /**
     * Map of response codes to headers.
     */
    private const HEADER_MAP = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        503 => 'Service Unavailable',
    ];

    /**
     * Task dispatcher: used to spawn workers for handling commands.
     */
    private Dispatcher $taskDispatcher;

    /**
     * Push transactions (whatever those are), indexed by:
     * * instance ID
     * * user ID
     * * pipe ID
     *
     * @var array<string, array<int, array<string, Transaction>>>
     */
    private array $pushTransactions = [];

    /**
     * User timeouts, indexed by instance ID and then by user ID
     *
     * @var array<string, array<int, TimerInterface>>
     */
    private array $userTimeouts = [];

    /**
     * File descriptor limit: if the number of descriptors this pipe manager
     * has open is above this limit, reject incoming connections.
     */
    private int $fdLimit;

    /**
     * @param LoopInterface             $reactLoop
     * @param PipeCollection            $pipes
     * @param ISubscriptionManager      $subscriptionManager
     * @param LoggerInterface           $log
     * @param DispatchStrategyInterface $strat
     * @param int                       $fdLimit
     */
    public function __construct(
        private LoopInterface $reactLoop,
        private PipeCollection $pipes,
        private ISubscriptionManager $subscriptionManager,
        private LoggerInterface $log,
        DispatchStrategyInterface $strat,
        int $fdLimit
    ) {
        $this->taskDispatcher = new Dispatcher($reactLoop, $this, $log, $strat);
        $this->fdLimit        = $fdLimit - self::FD_LIMIT_BUFFER;
        $this->log->debug('Instantiated PipeManager');
    }

    /**
     * Returns an instance-specific session save path.
     *
     * @todo move to some other class, which manages sessions directly
     *
     * @param string $redisUrl
     * @param string $instanceId
     * @return string
     */
    public static function getSessionSavePath(string $redisUrl, string $instanceId): string
    {
        return sprintf('%s?prefix=%s', $redisUrl, self::REDIS_SESSION_PREFIX_BASE . $instanceId . ':');
    }


    /**
     * When a new connection is opened it will be passed to this method
     *
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws Exception
     * @return void
     */
    public function onOpen(ConnectionInterface $conn): void
    {
        // receive new connection, register a pipe based on it
        $pipeCount = $this->pipes->count();

        $fdOpen = $pipeCount + 250; // just assume we have ~250 non pipe/socket file descriptors in use

        if ($fdOpen >= $this->fdLimit) {
            // we're about to go over our FD limit (which is fatal)
            $this->log->error("Open file descriptors has reached $fdOpen; rejecting client connection");
            $this->closeConnectionWithReason($conn, 503);
            return;
        }

        $pipe = new Pipe($conn);

        try {
            $this->log->info(sprintf('Received socket connection from %s', $pipe->getRemoteAddress()));
            $this->pipes->registerPipe($pipe);
        } catch (CommunicationException $e) {
            // couldn't talk to redis, send a retry back to the client
            $this->log->error('Communication Exception: ' . $e->getMessage(), [ 'exception' => $e ]);

            $this->sendHeader($pipe, 503, true);
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        } catch (SessionException $e) {
            // user not found, not logged in: signal authentication required
            // don't bother logging this unless debugging though?
            $this->log->debug('Failed to fully register pipe: ' . $e->getMessage(), [ 'exception' => $e ]);

            $this->sendHeader($pipe, 401);
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        }
    }


    /**
     * @param ConnectionInterface $conn
     * @param int                 $code
     */
    private function closeConnectionWithReason(ConnectionInterface $conn, int $code): void
    {
        // handle the connection raw
        $conn->send(
            json_encode([
                'error' => self::HEADER_MAP[$code],
                'code'  => [$code],
                'retry' => false,
            ])
        );

        $conn->close();
    }


    /**
     * This is called before or after a socket is closed (depends on how it's closed).
     * SendMessage to $conn will not result in an error if it has already been closed.
     *
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws Exception
     * @return void
     */
    public function onClose(ConnectionInterface $conn): void
    {
        // all WS are RFC6455, we can safely assume there's a pipe to unregister
        $socketKeyForConnection = Pipe::getSocketKeyForConnection($conn);
        $pipe                   = $this->pipes->getPipeBySocketKey($socketKeyForConnection);
        $this->pipes->unregisterPipe($socketKeyForConnection);
        $pipe?->close();
    }


    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown, the
     * Exception is sent back down the stack, handled by the Server and bubbled back up the application through this
     * method.
     *
     * @param  ConnectionInterface $conn
     * @param  Exception          $e
     * @throws Exception
     * @return void
     */
    public function onError(ConnectionInterface $conn, Exception $e): void
    {
//        if ($e instanceof ConnectionException) {
//            $this->log->warning("Connection to client lost, terminating");
//        }
//        else
        if (preg_match('`fwrite\(\): send of (\d+) bytes failed with errno=(\d+) (.+)`i', $e->getMessage(), $matches)) {
            $this->log->warning("IOException: {$matches[3]} [{$matches[2]}] ({$matches[1]} bytes dropped)");
        } else {
            $this->log->critical(
                sprintf('%s: "%s" occurred in %s on line %s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()),
                ['exception' => $e]
            );
        }

        // process exception needs to be rethrown
        if ($e instanceof ProcessException) {
            throw $e;
        }
    }


    /**
     * Triggered when a client sends data through the socket
     *
     * @param  ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string              $msg  The message received
     * @throws Exception
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $socketKey = Pipe::getSocketKeyForConnection($from);
        $pipe      = $this->pipes->getPipeBySocketKey($socketKey);

        if (!$pipe) {
            throw new ErrorException("Unrecognised pipe: '$socketKey'");
        }

        $instanceId = $pipe->getInstanceId();
        $userId     = $pipe->getUserId();
        $sessionId  = $pipe->getSession();

        $this->log->info(
            sprintf(
                'Received message: %s @ %s (%s) > %s',
                $userId ?: 'unknown',
                $socketKey,
                $sessionId ?: 'sessionless',
                $msg
            ),
            [ 'char' => '>' ]
        );

        // we'll just ignore messages that are of unknown origin
        if (!$userId) {
            return;
        }

        $data = json_decode($msg, true);

        // and we'll also ignore it if it doesn't parse as json in a way we expect (which is key => data)
        if (is_null($data) || count($data) !== 1) {
            $this->log->error("Bad data: $msg");
            return;
        }

        foreach ($data as $command => $value) {
            $arguments = [$userId, $value];

            if ($pipe->isLocked()) {
                // pipe has a command active: don't permit another until this one is finished
                $this->sendToPipe($pipe, json_encode([ $command => self::PIPE_METHOD_WAIT ]));
                return;
            }

            // set the pipe to "busy"
            $pipe->lock($command);

            // flag the user as active
            $this->refreshUser($instanceId, $userId);

            try {
                // don't flatly spawn a new worker: add the request to a queue
                $this->enqueue($pipe, $command, $arguments);
            } catch (QueueingException $e) {
                $this->log->critical("Failed to enqueue, task '$command' lost: " . $e->getMessage(), ['exception' => $e]);
                // since a full queue constitutes service unavailable, probably best to kick this pipe
                $this->sendHeader($pipe, 503, true);

                // cleanup
                $this->pipes->unregisterPipe($socketKey);
                $pipe->close();
            }

            // Return after processing the first command.
            // This maintains fidelity with the previous version, which used
            // `each()` once.
            return;
        }
    }


    /**
     * This method is invoked whenever something is published in Redis on one of our subscriptions
     *
     * @param Async\Event\IEvent $event
     * @return void
     */
    public function handle(Async\Event\IEvent $event): void
    {
        if (!$event instanceof Async\Event\Message) {
            // we're only interested in messages, no pongs
            return;
        }

        try {
            $action = $this->convertMessageToAction($event);
        } catch (InvalidArgumentException | ErrorException $e) {
            $this->log->warning("Malformed broadcast from Redis: {$e->getMessage()}");
            return;
        }

        if (!$this->pipes->userPipeCount($action->getInstanceId(), $action->getUserId())) {
            // this scenario will arise if the last socket for the user disconnects but a message arrived on their
            // subscription before it could be unsubscribed - just drop the message.
            return;
        }

        $action->execute($this);
    }


    /**
     * Factory method for converting a published redis message into a pipe action
     *
     * @param Async\Event\Message $msg
     * @throws InvalidArgumentException
     * @throws ErrorException
     * @return Action\IAction
     */
    private function convertMessageToAction(Async\Event\Message $msg): Action\IAction
    {
        if (preg_match('`^msg:(.+?):(\d+):(.*)$`', $msg->getChannel(), $matches)) {
            [, $instanceId, $userId, $method] = $matches;

            $this->log->debug("Received $method for $userId instance $instanceId", [ 'char' => '>' ]);

            // in all cases, we expect the payload to be a standard message
            $instance = Instance::jsonUnserialize($msg->getPayload());

            switch ($method) {
                case static::PIPE_METHOD_PUSH:
                    return new Action\Push($instanceId, (int)$userId, $instance);

                case static::PIPE_METHOD_ALERT:
                    return new Action\Alert($instanceId, (int)$userId, $instance);
            }
        }

        throw new InvalidArgumentException("Invalid channel supplied: '{$msg->getChannel()}'");
    }


    /**
     * When the initiator is backend (ie. something published on our subscription) the pipe method is going to be
     * an alert or a push. As such, it doesn't matter *which* pipe is used, because it will only be used as a basis
     * for broadcasting to all the pipes that belong to the same user. This method just retrieves *a* pipe for the user.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return Pipe
     */
    private function getFirstPipeForInstanceAndUser(string $instanceId, int $userId)
    {
        $pipes = $this->pipes->getPipesByInstanceAndUserId($instanceId, $userId);
        return reset($pipes);
    }


    /**
     * @param PipeInterface $pipe
     * @param string        $command
     * @param mixed         $payload
     * @throws QueueingException
     * @return void
     */
    private function enqueue(PipeInterface $pipe, string $command, mixed $payload): void
    {
        $this->taskDispatcher->enqueue($pipe, $command, $payload);
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @param string $command
     * @param mixed  $payload
     * @throws QueueingException
     * @return void
     */
    public function enqueueUser(string $instanceId, int $userId, string $command, mixed $payload): void
    {
        $this->enqueue($this->getFirstPipeForInstanceAndUser($instanceId, $userId), $command, $payload);
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @param string $key
     * @param mixed  $payload
     * @return void
     */
    public function transactUser(string $instanceId, int $userId, string $key, mixed $payload): void
    {
        $this->getTransaction($this->getFirstPipeForInstanceAndUser($instanceId, $userId))->put($key, $payload);
    }


    /**
     * @param  PipeInterface $pipe
     * @return Transaction
     */
    private function getTransaction(PipeInterface $pipe): Transaction
    {
        $instanceId = $pipe->getInstanceId();
        $userId     = $pipe->getUserId();
        $pipeId     = $pipe->getSocketKey();

        if (empty($this->pushTransactions[$instanceId][$userId][$pipeId])) {
            $this->pushTransactions[$instanceId][$userId][$pipeId] = new Transaction(
                $this->reactLoop,
                function ($data) use ($pipe, $userId) {

                    // the timer contains the data to be processed
                    $this->log->debug("Pushing cached messages to user $userId", [ 'char' => '<' ]);

                    try {
                        // enqueue to be dispatched
                        $this->enqueueInstances($pipe, $data);
                    } catch (QueueingException $e) {
                        $this->log->critical('Failed to enqueue, task lost: ' . $e->getMessage(), ['exception' => $e]);
                    } catch (InvalidArgumentException $e) {
                        $this->log->critical('Failed to enqueue, task lost: ' . $e->getMessage(), ['exception' => $e]);
                    }
                }
            );
        }

        return $this->pushTransactions[$instanceId][$userId][$pipeId];
    }


    /**
     * @param PipeInterface $pipe
     * @param Instance[]    $instances
     * @throws InvalidArgumentException
     * @throws QueueingException
     */
    private function enqueueInstances(PipeInterface $pipe, array $instances): void
    {
        if (empty($instances)) {
            return;
        }

        $this->enqueue(
            $pipe,
            self::PIPE_METHOD_PUSH,
            // run the instances through a decorator
            (new Decorator())->appendInstances($instances)->renderAsSocketArray(true)
        );
    }


    /**
     * Handles a response from a Worker
     *
     * @param Pipe   $pipe    - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command - the command that was executed by the Worker
     * @param array  $data    - the data with which the Worker responded
     * @return void           - this method does not return any value
     * @throws InvalidArgumentException
     * @throws QueueingException
     */
    public function handleResponse($pipe, $command, $data): void
    {
        $instanceId = $pipe->getInstanceId();
        $userId     = $pipe->getUserId();

        $this->log->notice("Handling a worker response: $command");

        $release = true;

        switch (strtolower($command)) {
            // deal with this yourself
            case self::PIPE_METHOD_SUBSCRIBE:
                if ($this->subscriptionManager->subscribeUser($instanceId, $userId)) {
                    $this->sendToPipe($pipe, [ self::PIPE_METHOD_SUBSCRIBE => true ]);

                    $this->enqueueInstances($pipe, $this->subscriptionManager
                        ->getUserSubscription($instanceId, $userId)
                        ->getUnread());
                } else {
                    // could not subscribe the user, rabbit-offline has already been reported, just inform the user
                    $this->sendToPipe($pipe, [ $command => false ]);
                }
                break;

            // this was dealt with by a worker, just push the result
            case self::PIPE_METHOD_FETCH:
                $data = $data[0];
                $this->sendToPipe($pipe, [ $command => $data ]);
                break;

            case self::PIPE_METHOD_PUSH:
                $this->sendToUser($instanceId, $userId, [ $command => $data ]);
                $release = false;
                break;

            // deal with this yourself
            case self::PIPE_METHOD_ACKNOWLEDGE:
                if ($subscription = $this->subscriptionManager->getUserSubscription($instanceId, $userId)) {
                    $markAsRead = [];
                    foreach ((array)$data[1] as $msgId) {
                        if ($subscription->acknowledgeMessage($msgId)) {
                            $markAsRead[] = $msgId;
                        }
                    }

                    $this->sendToUser($instanceId, $userId, [ $command => $markAsRead ]);
                }
                break;

            // alert behaves like push + ack
            case self::PIPE_METHOD_ALERT:
                $this->sendToUser($instanceId, $userId, [ $command => $data ]);
                $release = false;
                break;
        }

        // release the socket if permissible
        if ($release) {
            $pipe->unlock();
        }
    }


    /**
     * Handles an error from a Worker
     *
     * @param object $identifier - the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    - the command that was executed by the Worker
     * @param array  $data       - the data with which the Worker responded
     * @return void              - this method does not return any value
     */
    public function handleError($identifier, $command, $data): void
    {
        $this->log->notice("Received worker error for '$command': $data");
    }


    /**
     * @param Pipe $pipe
     * @param int  $code
     * @param bool $allowRetry
     * @return bool
     */
    private function sendHeader($pipe, $code, $allowRetry = false): bool
    {
        if (isset(self::HEADER_MAP[$code])) {
            $data = [
                'error' => self::HEADER_MAP[$code],
                'code'  => $code,
            ];

            if ($allowRetry) {
                $data['retry'] = true;
            }

            $this->sendToPipe($pipe, json_encode($data));

            return true;
        }

        return false;
    }


    /**
     * Send data to a specific pipe.
     *
     * @param Pipe         $pipe
     * @param string|array $data
     * @return void
     */
    private function sendToPipe($pipe, $data): void
    {
        $data  = is_string($data) ? $data : json_encode($data);
        $bytes = strlen($data);

        $this->log->debug("Sending $bytes bytes to {$pipe->getSocketKey()}", [ 'char' => '<' ]);
        $pipe->send($data);
    }


    /**
     * Send data to all pipes associated with a specific user.
     *
     * @param int   $userId
     * @param array $data
     * @return void
     */
    private function sendToUser(string $instanceId, int $userId, array $data): void
    {
        foreach ($this->pipes->getPipesByInstanceAndUserId($instanceId, $userId) as $pipe) {
            $this->sendToPipe($pipe, $data);
        }
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @return void
     */
    private function refreshUser(string $instanceId, int $userId): void
    {
        if (
            isset($this->userTimeouts[$instanceId][$userId])
            && $this->userTimeouts[$instanceId][$userId] instanceof TimerInterface
        ) {
            $this->reactLoop->cancelTimer($this->userTimeouts[$instanceId][$userId]);
            unset($this->userTimeouts[$instanceId][$userId]);
        }

        $this->userTimeouts[$instanceId][$userId] = $this->reactLoop->addTimer(
            self::INACTIVE_TIMEOUT,
            function () use ($instanceId, $userId) {
                $this->onTimeout($instanceId, $userId);
            }
        );
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @return void
     */
    private function onTimeout(string $instanceId, int $userId): void
    {
        $success = $this->subscriptionManager->removeSubscription($instanceId, $userId);

        // TODO consider parameterised logging
        $this->log->info(
            "User $userId for instance $instanceId has timed out, "
            . ($success ? 'unsubscribed' : 'failed to unsubscribe')
        );

        foreach ($this->pipes->getPipesByInstanceAndUserId($instanceId, $userId) as $pipe) {
            $this->sendHeader($pipe, 401);
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        }

        unset($this->userTimeouts[$instanceId][$userId], $this->pushTransactions[$instanceId][$userId]);
    }
}
