<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Pipe;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Redis\Async;
use Alaress\WorkerCrew\QueueingException;
use Alaress\WorkerCrew\Transaction\Transaction;
use ErrorException;
use InvalidArgumentException;
use Predis\CommunicationException;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\Pipe\Action\IAction;
use Schoolbox\Realtime\Pipe\Action;
use Schoolbox\Realtime\ProcessException;
use Schoolbox\Realtime\SessionException;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Schoolbox\Realtime\Subscription\ISubscriptionManager;

/**
 * This is the main "app" of our React loop.
 *
 * Class PipeManager
 *
 * @package Schoolbox\Realtime
 */
class PipeManager implements MessageComponentInterface, TaskResponseHandlerInterface, Async\EventListener
{

    public const REDIS_SESSION_PREFIX = 'PHPREDIS_SESSION:' . INSTANCE_ID . ':';
    const PHP_SESSION_PREFIX   = 'PHPSESSID';
    const INACTIVE_TIMEOUT     = 300; // 5 minutes

    const PIPE_METHOD_ACKNOWLEDGE = 'ack';
    const PIPE_METHOD_ALERT       = 'alert';
    const PIPE_METHOD_FETCH       = 'fetch';
    const PIPE_METHOD_PUSH        = 'push';
    const PIPE_METHOD_SUBSCRIBE   = 'subscribe';
    const PIPE_METHOD_WAIT        = 'wait';

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
    const FD_LIMIT_BUFFER = 16;

    /** @var string[] */
    private static $headerMap = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        503 => 'Service Unavailable',
    ];

    /** @var PipeCollection */
    private $pipes;

    /** @var ISubscriptionManager */
    private $subscriptionManager;

    /** @var LoopInterface */
    private $reactLoop;

    /** @var LoggerInterface */
    private $log;

    /** @var Dispatcher */
    private $taskDispatcher;

    /** @var Transaction[][] */
    private $pushTransactions;

    /** @var TimerInterface[] */
    private $userTimeouts = [];

    /** @var int */
    private $fdLimit;


    /**
     * @param LoopInterface             $reactLoop
     * @param PipeCollection            $pipes
     * @param ISubscriptionManager      $subscriptionManager
     * @param LoggerInterface           $log
     * @param DispatchStrategyInterface $strat
     * @param int                       $fdLimit
     */
    public function __construct(
        LoopInterface $reactLoop,
        PipeCollection $pipes,
        ISubscriptionManager $subscriptionManager,
        LoggerInterface $log,
        DispatchStrategyInterface $strat,
        $fdLimit
    ) {
        $this->reactLoop           = $reactLoop;
        $this->log                 = $log;
        $this->subscriptionManager = $subscriptionManager;
        $this->pipes               = $pipes;
        $this->taskDispatcher      = new Dispatcher($reactLoop, $this, $log, $strat);
        $this->fdLimit             = $fdLimit - self::FD_LIMIT_BUFFER;

        $this->log->debug('Instantiated PipeManager');
    }

    /**
     * Returns an instance-specific session save path
     *
     * @param string $redisUrl
     * @return string
     */
    public static function getSessionSavePath(string $redisUrl): string
    {
        return sprintf('%s?prefix=%s', $redisUrl, self::REDIS_SESSION_PREFIX);
    }


    /**
     * When a new connection is opened it will be passed to this method
     *
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     * @return void
     */
    public function onOpen(ConnectionInterface $conn)
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
            $this->log->notice('Failed to fully register pipe: ' . $e->getMessage(), [ 'exception' => $e ]);

            $this->sendHeader($pipe, 401);
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        }
    }


    /**
     * @param ConnectionInterface $conn
     * @param int                 $code
     */
    private function closeConnectionWithReason(ConnectionInterface $conn, $code)
    {
        // handle the connection raw
        $conn->send(
            json_encode([
                'error' => self::$headerMap[$code],
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
     * @throws \Exception
     * @return void
     */
    public function onClose(ConnectionInterface $conn)
    {
        // all WS are RFC6455, we can safely assume there's a pipe to unregister
        $socketKeyForConnection = Pipe::getSocketKeyForConnection($conn);
        $pipe                   = $this->pipes->getPipeBySocketKey($socketKeyForConnection);
        $this->pipes->unregisterPipe($socketKeyForConnection);
        if (null !== $pipe) {
            $pipe->close();
        }
    }


    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown, the
     * Exception is sent back down the stack, handled by the Server and bubbled back up the application through this
     * method.
     *
     * @param  ConnectionInterface $conn
     * @param  \Exception          $e
     * @throws \Exception
     * @return void
     */
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
//        if ($e instanceof ConnectionException) {
//            $this->log->warning("Connection to client lost, terminating");
//        }
//        else
        if (preg_match('`fwrite\(\): send of (\d+) bytes failed with errno=(\d+) (.+)`', $e->getMessage(), $matches)) {
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
     * @throws \Exception
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $socketKey = Pipe::getSocketKeyForConnection($from);
        $pipe      = $this->pipes->getPipeBySocketKey($socketKey);

        if (!$pipe) {
            throw new ErrorException("Unrecognised pipe: '$socketKey'");
        }

        $userId    = $pipe->getUserId();
        $sessionId = $pipe->getSession();

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

        $request   = each($data);
        $command   = $request['key'];
        $arguments = [ $userId, $request['value'] ];

        if ($pipe->isLocked()) {
            // pipe has a command active: don't permit another until this one is finished
            $this->sendToPipe($pipe, json_encode([ $command => self::PIPE_METHOD_WAIT ]));

            return;
        }

        // set the pipe to "busy"
        $pipe->lock($command);

        // flag the user as active
        $this->refreshUser($userId);

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
    }


    /**
     * This method is invoked whenever something is published in Redis on one of our subscriptions
     *
     * @param Async\Event\IEvent $event
     * @return void
     */
    public function handle(Async\Event\IEvent $event)
    {
        if (!$event instanceof Async\Event\Message) {
            // we're only interested in messages, no pongs
            return;
        }

        try {
            $action = $this->convertMessageToAction($event);
        } catch (InvalidArgumentException $e) {
            $this->log->warning("Malformed broadcast from Redis: {$e->getMessage()}");
            return;
        } catch (ErrorException $e) {
            $this->log->warning("Malformed broadcast from Redis: {$e->getMessage()}");
            return;
        }

        if (!$this->pipes->userPipeCount($action->getUserId())) {
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
     * @return IAction
     */
    private function convertMessageToAction(Async\Event\Message $msg)
    {
        if (preg_match('`^msg:.*:(\d+):(.*)$`', $msg->getChannel(), $matches)) {
            $userId = $matches[1];

            $this->log->debug("Received {$matches[2]} for {$userId}", [ 'char' => '>' ]);

            // in all cases, we expect the payload to be a standard message
            $instance  = Instance::jsonUnserialize($msg->getPayload());

            switch ($matches[2]) {
                case static::PIPE_METHOD_PUSH:
                    return new Action\Push($userId, $instance);

                case static::PIPE_METHOD_ALERT:
                    return new Action\Alert($userId, $instance);
            }
        }

        throw new InvalidArgumentException("Invalid channel supplied: '{$msg->getChannel()}'");
    }


    /**
     * When the initiator is backend (ie. something published on our subscription) the pipe method is going to be
     * an alert or a push. As such, it doesn't matter *which* pipe is used, because it will only be used as a basis
     * for broadcasting to all the pipes that belong to the same user. This method just retrieves *a* pipe for the user.
     *
     * @param int $userId
     * @return Pipe
     */
    private function getFirstPipeForUser($userId)
    {
        return reset($this->pipes->getPipesByUserId($userId));
    }


    /**
     * @param PipeInterface $pipe
     * @param string        $command
     * @param mixed         $payload
     * @throws QueueingException
     * @return void
     */
    private function enqueue(PipeInterface $pipe, $command, $payload)
    {
        $this->taskDispatcher->enqueue($pipe, $command, $payload);
    }


    /**
     * @param int    $userId
     * @param string $command
     * @param mixed  $payload
     * @throws QueueingException
     * @return void
     */
    public function enqueueUser($userId, $command, $payload)
    {
        $this->enqueue($this->getFirstPipeForUser($userId), $command, $payload);
    }


    /**
     * @param int    $userId
     * @param string $key
     * @param mixed  $payload
     * @return void
     */
    public function transactUser($userId, $key, $payload)
    {
        $this->getTransaction($this->getFirstPipeForUser($userId))->put($key, $payload);
    }


    /**
     * @param  PipeInterface $pipe
     * @return Transaction
     */
    private function getTransaction(PipeInterface $pipe)
    {
        $userId = $pipe->getUserId();
        $pipeId = $pipe->getSocketKey();

        if (empty($this->pushTransactions[$userId][$pipeId])) {
            $this->pushTransactions[$userId][$pipeId] = new Transaction(
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

        return $this->pushTransactions[$userId][$pipeId];
    }


    /**
     * @param PipeInterface $pipe
     * @param Instance[]    $instances
     * @throws InvalidArgumentException
     * @throws QueueingException
     */
    private function enqueueInstances(PipeInterface $pipe, array $instances)
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
    public function handleResponse($pipe, $command, $data)
    {
        $userId = $pipe->getUserId();

        $this->log->notice("Handling a worker response: $command");

        $release = true;

        switch (strtolower($command)) {
            // deal with this yourself
            case self::PIPE_METHOD_SUBSCRIBE:
                if ($this->subscriptionManager->subscribeUser($userId)) {
                    $this->sendToPipe($pipe, [ self::PIPE_METHOD_SUBSCRIBE => true ]);

                    $this->enqueueInstances($pipe, $this->subscriptionManager
                        ->getUserSubscription($userId)
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
                $this->sendToUser($userId, [ $command => $data ]);
                $release = false;
                break;

            // deal with this yourself
            case self::PIPE_METHOD_ACKNOWLEDGE:
                if ($subscription = $this->subscriptionManager->getUserSubscription($userId)) {
                    $markAsRead = [];
                    foreach ((array)$data[1] as $msgId) {
                        if ($subscription->acknowledgeMessage($msgId)) {
                            $markAsRead[] = $msgId;
                        }
                    }

                    $this->sendToUser($userId, [ $command => $markAsRead ]);
                }
                break;

            // alert behaves like push + ack
            case self::PIPE_METHOD_ALERT:
                $this->sendToUser($userId, [ $command => $data ]);
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
    public function handleError($identifier, $command, $data)
    {
        $this->log->notice("Received worker error for '$command': $data");
    }


    /**
     * @param Pipe $pipe
     * @param int  $code
     * @param bool $allowRetry
     * @return bool
     */
    private function sendHeader($pipe, $code, $allowRetry = false)
    {
        if (isset(static::$headerMap[$code])) {
            $data = [
                'error' => static::$headerMap[$code],
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
    private function sendToPipe($pipe, $data)
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
    private function sendToUser($userId, $data)
    {
        foreach ($this->pipes->getPipesByUserId($userId) as $pipe) {
            $this->sendToPipe($pipe, $data);
        }
    }


    /**
     * @param int $userId
     * @return void
     */
    private function refreshUser($userId)
    {
        if ($this->userTimeouts[$userId] instanceof TimerInterface) {
            $this->reactLoop->cancelTimer($this->userTimeouts[$userId]);
            unset($this->userTimeouts[$userId]);
        }

        $this->userTimeouts[$userId] = $this->reactLoop->addTimer(
            self::INACTIVE_TIMEOUT,
            function () use ($userId) {
                $this->onTimeout($userId);
            }
        );
    }


    /**
     * @param int $userId
     * @return void
     */
    private function onTimeout($userId)
    {
        $success = $this->subscriptionManager->removeSubscription($userId);
        $this->log->info("User $userId has timed out, " . ($success ? 'unsubscribed' : 'failed to unsubscribe'));

        foreach ($this->pipes->getPipesByUserId($userId) as $pipe) {
            $this->sendHeader($pipe, 401);
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        }

        unset($this->userTimeouts[$userId], $this->pushTransactions[$userId]);
    }


    /**
     * @unused
     * Send data to all pipes associated with a specific session.
     *
     * @param string $sessionId
     * @param string $data
     * @return void
     */
    protected function sendToSession($sessionId, $data)
    {
        foreach ($this->pipes->getPipesBySessionId($sessionId) as $pipe) {
            $this->sendToPipe($pipe, $data);
        }
    }


    /**
     * @unused
     * Unregister all pipes associated with a session.
     *
     * @param string $sessionId
     * @return void
     */
    protected function unregisterAllPipesBySessionId($sessionId)
    {
        foreach ($this->pipes->getPipesBySessionId($sessionId) as $pipe) {
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        }
    }


    /**
     * @unused
     * Unregister all pipes associated with a user.
     *
     * @param int $userId
     * @return void
     */
    protected function unregisterAllPipesByUserId($userId)
    {
        foreach ($this->pipes->getPipesByUserId($userId) as $pipe) {
            $this->pipes->unregisterPipe($pipe->getSocketKey());
            $pipe->close();
        }
    }
}
