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
use Schoolbox\MultiInstance\InstanceRepository;
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
    public const REDIS_SESSION_PREFIX = self::REDIS_SESSION_PREFIX_BASE . INSTANCE_ID . ':';

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
     * Constructor
     *
     * @param LoopInterface             $reactLoop           React loop: required for this object's task dispatcher to
     *                                                       listen to job progress, and for timing out sessions.
     * @param InstanceRepository        $instanceRepository  Repository of instances: required so that incoming
     *                                                       connections may be associated with an instance.
     * @param PipeCollection            $pipes               Collection of pipes this manager manages.
     * @param ISubscriptionManager      $subscriptionManager Subscribes and unsubscribes users from sockets
     * @param LoggerInterface           $log                 Logger
     * @param DispatchStrategyInterface $strat               Strategy for dispatching jobs to the task dispatcher?
     * @param int                       $fdLimit             Configurable limit of file descriptors: in practice,
     *                                                       `self::FD_LIMIT_BUFFER` descriptors are left unused
     */
    public function __construct(
        private LoopInterface $reactLoop,
        private InstanceRepository $instanceRepository,
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

        // Extract the instance ID, and inject into pipe
        try {
            $instanceConfig = $this->instanceRepository->fetchConfigForHostname(
                Pipe::extractWebSocketRequest($conn)->getUri()->getHost()
            );
            $pipe           = new Pipe($conn, $instanceConfig->instanceId);
        } catch (\OutOfBoundsException $e) {
            /**
             * \Schoolbox\MultiInstance\InstanceRepository::fetchConfigForInstance also throws
             * OutOfBoundsException, however, I think it should be safe to assume it is thrown when
             * hostname is unknown as the $instanceId should always be set since it's loaded from the array
             */
            $this->log->error('Connection from unknown hostname, exiting:  ' . $e->getMessage());
            /**
             * Exit with EX_NOHOST code
             * Wait for the systemd to restart the daemon to reload hostname
             */
            exit(68);
        }

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
     * Close a connection, with an associated error code.
     *
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
        if (preg_match('`fwrite\(\): send of (\d+) bytes failed with errno=(\d+) (.+)`i', $e->getMessage(), $matches)) {
            $this->log->warning("IOException: $matches[3] [$matches[2]] ($matches[1] bytes dropped)");
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
            $arguments = [$instanceId, $userId, $value];

            if ($pipe->isLocked()) {
                // pipe has a command active: don't permit another until this one is finished
                $this->sendToPipe($pipe, [ $command => self::PIPE_METHOD_WAIT ]);
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

            $this->log->debug("Received $method for instance:user $instanceId:$userId ", [ 'char' => '>' ]);

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
     * @return Pipe|null
     */
    private function getFirstPipeForInstanceAndUser(string $instanceId, int $userId): ?Pipe
    {
        $pipes     = $this->pipes->getPipesByInstanceAndUserId($instanceId, $userId);
        $firstPipe = reset($pipes);
        return $firstPipe instanceof Pipe ? $firstPipe : null;
    }

    /**
     * Enqueue a command through a pipe to the task dispatcher.
     *
     * The command's output will then be output to the pipe.
     *
     * @param Pipe   $pipe
     * @param string $command
     * @param mixed  $payload
     * @throws QueueingException
     * @return void
     */
    private function enqueue(Pipe $pipe, string $command, mixed $payload): void
    {
        $this->taskDispatcher->enqueue($pipe, $command, $payload);
    }

    /**
     *  Enqueue a command through the first pipe for the given user.
     *
     * @param string $instanceId
     * @param int    $userId
     * @param string $command
     * @param mixed  $payload
     * @return void
     * @throws QueueingException
     */
    public function enqueueUser(string $instanceId, int $userId, string $command, mixed $payload): void
    {
        $pipe = $this->getFirstPipeForInstanceAndUser($instanceId, $userId);
        if ($pipe) {
            $this->enqueue($pipe, $command, $payload);
        }
    }

    /**
     * Write a payload to the transaction of the first pipe for the given user.
     *
     * Used within `\Schoolbox\Realtime\Pipe\Action\Push` to write data to an
     * already open pipe?
     *
     * @param string $instanceId
     * @param int    $userId
     * @param string $key
     * @param mixed  $payload
     * @return void
     */
    public function transactUser(string $instanceId, int $userId, string $key, mixed $payload): void
    {
        $pipe = $this->getFirstPipeForInstanceAndUser($instanceId, $userId);
        if ($pipe) {
            $this->getTransaction($pipe)->put($key, $payload);
        }
    }

    /**
     * Retrieve the transaction for the given pipe.
     *
     * @param  Pipe $pipe
     * @return Transaction
     */
    private function getTransaction(Pipe $pipe): Transaction
    {
        $instanceId = $pipe->getInstanceId();
        $userId     = $pipe->getUserId();
        $pipeId     = $pipe->getSocketKey();

        if (empty($this->pushTransactions[$instanceId][$userId][$pipeId])) {
            $this->pushTransactions[$instanceId][$userId][$pipeId] = new Transaction(
                $this->reactLoop,
                function ($data) use ($pipe, $instanceId, $userId): void {

                    // the timer contains the data to be processed
                    $this->log->debug("Pushing cached messages to instance:user $instanceId:$userId", [ 'char' => '<' ]);

                    try {
                        // enqueue to be dispatched
                        $this->enqueueInstances($pipe, $data);
                    } catch (QueueingException | InvalidArgumentException $e) {
                        $this->log->critical('Failed to enqueue, task lost: ' . $e->getMessage(), ['exception' => $e]);
                    }
                }
            );
        }

        return $this->pushTransactions[$instanceId][$userId][$pipeId];
    }

    /**
     * Pushes a payload generated from `$instances` to the given pipe.
     *
     * @param Pipe       $pipe
     * @param Instance[] $instances
     * @throws InvalidArgumentException
     * @throws QueueingException
     */
    private function enqueueInstances(Pipe $pipe, array $instances): void
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
     * @param Pipe   $identifier the identifier passed as the first argument to Dispatcher::enqueue()
     * @param string $command    the command that was executed by the Worker
     * @param array  $data       the data with which the Worker responded
     * @return void
     * @throws InvalidArgumentException
     * @throws QueueingException
     */
    public function handleResponse($identifier, $command, $data): void
    {
        $instanceId = $identifier->getInstanceId();
        $userId     = $identifier->getUserId();

        $this->log->debug("Handling a worker response for $instanceId: $command");

        $release = true;

        switch (strtolower($command)) {
            // deal with this yourself
            case self::PIPE_METHOD_SUBSCRIBE:
                if ($this->subscriptionManager->subscribeUser($instanceId, $userId)) {
                    $this->sendToPipe($identifier, [ self::PIPE_METHOD_SUBSCRIBE => true ]);

                    $this->enqueueInstances($identifier, $this->subscriptionManager
                        ->getUserSubscription($instanceId, $userId)
                        ->getUnread());
                } else {
                    // could not subscribe the user, rabbit-offline has already been reported, just inform the user
                    $this->sendToPipe($identifier, [ $command => false ]);
                }
                break;

            // this was dealt with by a worker, just push the result
            case self::PIPE_METHOD_FETCH:
                $data = $data[0];
                $this->sendToPipe($identifier, [ $command => $data ]);
                break;

            // alert behaves like push + ack: either way it's sent the same way
            case self::PIPE_METHOD_ALERT:
            case self::PIPE_METHOD_PUSH:
                $this->sendToUser($instanceId, $userId, [ $command => $data ]);
                $release = false;
                break;

            // deal with this yourself
            case self::PIPE_METHOD_ACKNOWLEDGE:
                $subscription     = $this->subscriptionManager->getUserSubscription($instanceId, $userId);
                $markAsRead       = [];
                [, , $messageIds] = $data;
                if (!is_iterable($messageIds)) {
                    $messageIds = [$messageIds];
                }
                foreach ($messageIds as $msgId) {
                    if (is_string($msgId)) {
                        if ($subscription->acknowledgeMessage($msgId)) {
                            $markAsRead[] = $msgId;
                        }
                    } else {
                        $this->log->info(sprintf("Command '%s' message ID '%s' is not a string: ignoring", $command, $msgId));
                    }
                }

                $this->sendToUser($instanceId, $userId, [ $command => $markAsRead ]);
                break;

            default:
                $this->log->info(sprintf("Command '%s' not recognized: ignoring", $command));
                break;
        }

        // release the socket if permissible
        if ($release) {
            $identifier->unlock();
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
        $this->log->notice("Received worker error for '$command': " . json_encode($data));
    }

    /**
     * Send a raw header to a pipe.
     *
     * @param Pipe $pipe
     * @param int  $code
     * @param bool $allowRetry
     * @return bool
     */
    private function sendHeader(Pipe $pipe, int $code, bool $allowRetry = false): bool
    {
        if (isset(self::HEADER_MAP[$code])) {
            $data = [
                'error' => self::HEADER_MAP[$code],
                'code'  => $code,
            ];

            if ($allowRetry) {
                $data['retry'] = true;
            }

            $this->sendToPipe($pipe, $data);

            return true;
        }

        return false;
    }

    /**
     * Send data to a specific pipe.
     *
     * @param Pipe  $pipe
     * @param array $data
     * @return void
     */
    private function sendToPipe(Pipe $pipe, array $data): void
    {
        $encodedData = json_encode($data);
        $bytes = strlen($encodedData);

        $this->log->debug("Sending $bytes bytes to {$pipe->getSocketKey()}", [ 'char' => '<' ]);
        $pipe->send($encodedData);
    }

    /**
     * Send data to all pipes associated with a specific user.
     *
     * @param string $instanceId
     * @param int    $userId
     * @param array  $data
     * @return void
     */
    private function sendToUser(string $instanceId, int $userId, array $data): void
    {
        foreach ($this->pipes->getPipesByInstanceAndUserId($instanceId, $userId) as $pipe) {
            $this->sendToPipe($pipe, $data);
        }
    }

    /**
     * Refresh all timeouts associated with this user in this instance.
     *
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
            function () use ($instanceId, $userId): void {
                $this->onTimeout($instanceId, $userId);
            }
        );
    }

    /**
     * Timeout callback for a given user and instance: unregisters all pipes for
     * that user/instance.
     *
     * @param string $instanceId
     * @param int    $userId
     * @return void
     */
    private function onTimeout(string $instanceId, int $userId): void
    {
        $success = $this->subscriptionManager->removeSubscription($instanceId, $userId);

        // TODO consider parameterised logging
        $this->log->info(
            "Instance:user $instanceId:$userId has timed out, "
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
