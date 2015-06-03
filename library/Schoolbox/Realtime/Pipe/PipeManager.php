<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Alaress\Async\AMQP\AMQPResponseHandlerInterface;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Cloud;
use Alaress\WorkerCrew\QueueingException;
use Alaress\WorkerCrew\Transaction\Transaction;
use PhpAmqpLib\Message\AMQPMessage;
use Predis\CommunicationException;
use Psr\Log\LoggerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\Timer\TimerInterface;
use React\Socket\ConnectionException;
use React\Stomp\Client;
use Schoolbox\MobileApp\ServiceManager;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\ProcessException;
use Schoolbox\Realtime\SessionException;
use Schoolbox\Realtime\Stomp\SubscriptionException;
use Schoolbox\Realtime\Stomp\SubscriptionManager;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * This is the main "app" of our React loop.
 *
 * Class PipeManager
 * @package Schoolbox\Realtime
 */
class PipeManager implements WampServerInterface, MessageComponentInterface, TaskResponseHandlerInterface, AMQPResponseHandlerInterface {

	const REDIS_SESSION_PREFIX = 'PHPREDIS_SESSION:';
	const PHP_SESSION_PREFIX   = 'PHPSESSID';
    const INACTIVE_TIMEOUT     = 300; // 5 minutes

    const PIPE_METHOD_ACKNOWLEDGE = 'ack';
    const PIPE_METHOD_ALERT       = 'alert';
    const PIPE_METHOD_FETCH       = 'fetch';
    const PIPE_METHOD_PUSH        = 'push';
    const PIPE_METHOD_SUBSCRIBE   = 'subscribe';
    const PIPE_METHOD_WAIT        = 'wait';


	/** @var PipeCollection */
	protected $pipes;

	/** @var SubscriptionManager */
	protected $subscriptionManager;

    /** @var ServiceManager */
    protected $cloudManager;

	/** @var LoopInterface */
	protected $reactLoop;

	/** @var LoggerInterface */
	protected $log;

	/** @var Dispatcher */
	protected $taskDispatcher;

	/** @var Transaction[] */
	protected $pushTransactions;

    /** @var array */
    protected $activeCommands;

    /** @var TimerInterface[] */
    protected $userTimeouts = array();

    /**
     * @param LoopInterface             $reactLoop
     * @param SessionValidator          $sessionValidator
     * @param Client                    $stompClient
     * @param QueueFactory              $qf
     * @param ServiceManager            $cloudManager
     * @param LoggerInterface           $log
     * @param DispatchStrategyInterface $strat
     */
    public function __construct(
        LoopInterface $reactLoop,
        SessionValidator $sessionValidator,
        Client $stompClient,
        QueueFactory $qf,
        ServiceManager $cloudManager,
        LoggerInterface $log,
        DispatchStrategyInterface $strat = null
    ) {
        $this->pipes               = new PipeCollection($sessionValidator, $log);
        $this->reactLoop           = $reactLoop;
        $this->taskDispatcher      = new Dispatcher($reactLoop, $this, $log, $strat);
        $this->log                 = $log;
        $this->cloudManager        = $cloudManager;
        $this->subscriptionManager = new SubscriptionManager($stompClient, $this, $log, $qf);

        $this->log->debug('Instantiated PipeManager');
    }


	/**
	 * When a new connection is opened it will be passed to this method
	 *
	 * @param  ConnectionInterface $conn The socket/connection that just connected to your application
	 * @throws \Exception
	 */
	public function onOpen(ConnectionInterface $conn) {

		// receive new connection, register a pipe based on it
		$pipe = new Pipe($conn);

		$this->log->info(sprintf('Received socket connection from %s', $pipe->getRemoteAddress()));

		try {
			$this->pipes->registerPipe($pipe);
		}
		catch(CommunicationException $e) {
			// couldn't talk to redis, send a retry back to the client
			$this->log->error("Communication Exception" . $e->getMessage(), array($e));

			$this->sendHeader($pipe, 503, true);
			$pipe->close();
		}
		catch(SessionException $e) {
			// user not found, not logged in: signal authentication required
			$this->log->error('Failed to fully register pipe: '.$e->getMessage(), array($e));

			$this->sendHeader($pipe, 401);
			$pipe->close();
		}
	}


	/**
	 * This is called before or after a socket is closed (depends on how it's closed).
	 * SendMessage to $conn will not result in an error if it has already been closed.
	 *
	 * @param  ConnectionInterface $conn The socket/connection that is closing/closed
	 * @throws \Exception
	 */
	public function onClose(ConnectionInterface $conn) {
        /** @noinspection PhpUndefinedFieldInspection - explicitly close the descriptor to the temporary-file associated with the request that initiated this conn */
		$conn->WebSocket->request->getBody()->close();
        $key = Pipe::getSocketKeyForConnection($conn);
		$this->pipes->unregisterPipe($key);
        unset($this->activeCommands[$key]);
	}


	/**
	 * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
	 * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
	 *
	 * @param  ConnectionInterface $conn
	 * @param  \Exception $e
	 * @throws \Exception
	 */
	public function onError(ConnectionInterface $conn, \Exception $e) {
		$this->log->critical(sprintf('%s: "%s" - %s:%s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));

		// process exception needs to be rethrown
		if($e instanceof ProcessException) {
			throw $e;
		}
	}


	/**
	 * Triggered when a client sends data through the socket
	 *
	 * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
	 * @param  string $msg The message received
	 * @throws \Exception
	 */
	public function onMessage(ConnectionInterface $from, $msg) {
		$socketKey = Pipe::getSocketKeyForConnection($from);

		if(!($pipe = $this->pipes->getPipeBySocketKey($socketKey))) {
			throw new ConnectionException("Unrecognised pipe: '$socketKey'");
		}

		$userId = $pipe->getUserId();
		$sessionId = $pipe->getSession() ?: 'sessionless';

		$this->log->info(sprintf('Received message: %s (%s:%s) > %s', $userId ?: 'unknown', $socketKey, $sessionId, $msg), array('char' => '>'));

		// we'll just ignore messages that are of unknown origin
		if(!($userId && $sessionId)) {
			return;
		}

		$data = json_decode($msg, true);

		// and we'll also ignore it if it doesn't parse as json in a way we expect (which is key => data)
		if(is_null($data) || count($data) != 1) {
			return;
		}

		$request = each($data);

		$command = $request['key'];
		$arguments = array($userId, $request['value']);

        if(isset($this->activeCommands[$socketKey])) {
            // pipe has a command active: don't permit another until this one is finished
            $this->sendToPipe($pipe, json_encode(array(
                $command => self::PIPE_METHOD_WAIT
            )));
            return;
        }

        // set the pipe to "busy"
        $this->activeCommands[$socketKey] = $command;

        // flag the user as active
        $this->refreshUser($userId);

		// don't flatly spawn a new worker: add the request to a queue
        try {
            $this->taskDispatcher->enqueue($pipe, $command, $arguments);
        }
        catch(QueueingException $e) {
            $this->log->critical("Failed to enqueue, task '$command' lost: ".$e->getMessage());
            // since a full queue constitutes service unavailable, probably best to kick this pipe
            $this->sendHeader($pipe, 503, true);

            // cleanup
            unset($this->activeCommands[$socketKey]);
            $this->pipes->unregisterPipe($socketKey);
            $pipe->close();
        }

	}


    /**
     * This is the method called from within a Subscription object for notifying attached sockets of a new message.
     *
     * @param AMQPMessage $message
     * @param int         $userId
     * @return bool
     * @throws ProcessException
     */
    public function onAMQP($message, $userId)
    {

        if (!$this->pipes->userPipeCount($userId)) {
            return false;
        }

        /*
         * When the initiator is AMQP (ie. something new in Rabbit), the pipe method is going to be an alert or a push.
         * As such, it doesn't matter *which* pipe is used, because it will only be used as a basis for broadcasting to
         * all the pipes that belong to the same user.
         */
        $pipe = reset($this->pipes->getPipesByUserId($userId));

        $formatted = json_decode($message->body, true);
        $instance  = Instance::jsonUnserialize($formatted['message']);

        // if this is an alert, bypass transactionality
        if ($instance->getNamespace() == 'ALERT') {
            $decorator = new Decorator();
            $decorator->appendInstance($instance);
            $this->taskDispatcher->enqueue($pipe, self::PIPE_METHOD_ALERT, $decorator->renderAsSocketArray(true));
            return true;
        }

        $hash = sha1($formatted['message']);

        $this->getTransaction($pipe)->put($hash, $instance);

        return true;
    }


    /**
     * @param  Pipe $pipe
     * @return Transaction
     */
    protected function getTransaction($pipe)
    {
        $userId = $pipe->getUserId();
        $pipeId = $pipe->getSocketKey();

        if (empty($this->pushTransactions[$userId][$pipeId])) {
            $this->pushTransactions[$userId][$pipeId] = new Transaction($this->reactLoop,
                function($timer) use ($pipe, $userId) {
                    /** @var Timer $timer */

                    // the timer contains the data to be processed
                    $this->log->info("Pushing cached messages to user $userId", array('char' => '>'));

                    // run the instances through a decorator
                    $decorator = new Decorator();
                    $decorator->appendInstances($timer->getData());

                    // enqueue to be dispatched
                    try {
                        $this->taskDispatcher->enqueue($pipe, self::PIPE_METHOD_PUSH, $decorator->renderAsSocketArray(true));

                    } catch(QueueingException $e) {
                        $this->log->critical('Failed to enqueue, task lost: '.$e->getMessage());
                    }
                }
            );
        }

        return $this->pushTransactions[$userId][$pipeId];
    }


    /**
     * @param TimerInterface $timer
     */
    public function onTimeout($timer) {
        $userId = $timer->getData();
        $success = $this->subscriptionManager->removeSubscription($userId);
        $this->log->info("User $userId has timed out, " . ($success ? "unsubscribed" : "failed to unsubscribe"));

        foreach($this->pipes->getPipesByUserId($userId) as $pipe) {
            $this->sendHeader($pipe, 401);
            $pipe->close();
        }

        unset($this->userTimeouts[$userId]);
        unset($this->pushTransactions[$userId]);
    }


	/**
	 * Unregister all pipes associated with a session.
	 *
	 * @param string $sessionId
	 */
	protected function unregisterAllPipesBySessionId($sessionId) {
		foreach($this->pipes->getPipesBySessionId($sessionId) as $pipe) {
			$this->pipes->unregisterPipe($pipe->getSocketKey());
		}
	}


	/**
	 * Unregister all pipes associated with a user.
	 *
	 * @param int $userId
	 */
	protected function unregisterAllPipesByUserId($userId) {
		foreach($this->pipes->getPipesByUserId($userId) as $pipe) {
			$this->pipes->unregisterPipe($pipe->getSocketKey());
		}
	}


	/**
	 * Handles a response from a Worker
	 *
	 * @param Pipe   $pipe       - the identifier passed as the first argument to Dispatcher::enqueue()
	 * @param string $command    - the command that was executed by the Worker
	 * @param array  $data       - the data with which the Worker responded
	 * @return void              - this method does not return any value
	 */
	public function handleResponse($pipe, $command, $data) {

		$userId = $pipe->getUserId();

		$this->log->notice("Handling a worker response: $command");

        $release = true;

		switch(strtolower($command)) {
			// deal with this yourself
            case self::PIPE_METHOD_SUBSCRIBE:
				try {
					if ($this->subscriptionManager->subscribe($userId)) {
                        $this->sendToPipe($pipe, array(self::PIPE_METHOD_SUBSCRIBE => true));
                        $this->subscriptionManager->getUserSubscription($userId)->push();
					}
				}
				catch(SubscriptionException $e) {
					// could not subscribe the user, rabbit-offline has already been reported, just inform the user
					$this->sendToPipe($pipe, array($command => false));
				}
				break;

			// this was dealt with by a worker, just push the result
            case self::PIPE_METHOD_FETCH:
				$data = $data[0];
				$this->sendToPipe($pipe, array($command => $data));
				break;

            case self::PIPE_METHOD_PUSH:
				$this->sendToUser($userId, array($command => $data));
                $release = false;
				break;

			// deal with this yourself
            case self::PIPE_METHOD_ACKNOWLEDGE:
				if($subscription = $this->subscriptionManager->getUserSubscription($userId, true)) {
					$markAsRead = $subscription->acceptMessages($data[1]);
					$this->sendToUser($userId, array($command => $markAsRead));
				}
				break;

            // alert behaves like push + ack
            case self::PIPE_METHOD_ALERT:
                $this->sendToUser($userId, array($command => $data));
                $release = false;
                if($subscription = $this->subscriptionManager->getUserSubscription($userId, true)) {
                    $subscription->acceptMessages(array_keys($data));
                }
                break;
		}

        // release the socket if permissible
        if($release) {
            unset($this->activeCommands[$pipe->getSocketKey()]);
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
     * @param int $code
     * @param bool $allowRetry
     * @return bool
     */
    public function sendHeader($pipe, $code, $allowRetry = false) {
        $map = array(
            401 => 'Unauthorized',
            503 => 'Service Unavailable'
        );

        if(isset($map[$code])) {
            $data = array(
                'error' => $map[$code],
                'code'  => $code
            );

            if($allowRetry) {
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
	 * @param Pipe $pipe
	 * @param string|array $data
	 */
	public function sendToPipe($pipe, $data) {
		$data = is_string($data) ? $data : json_encode($data);
		$bytes = strlen($data);
		$this->log->info("Sending $bytes bytes to {$pipe->getSocketKey()}", array('char' => '<'));
		$pipe->send($data);
	}


	/**
	 * Send data to all pipes associated with a specific session.
	 *
	 * @param string $sessionId
	 * @param string $data
	 */
	public function sendToSession($sessionId, $data) {
		foreach($this->pipes->getPipesBySessionId($sessionId) as $pipe) {
			$this->sendToPipe($pipe, $data);
		}
	}


	/**
	 * Send data to all pipes associated with a specific user.
	 *
	 * @param int    $userId
	 * @param string $data
	 * @param bool   $cloudPush
	 */
	public function sendToUser($userId, $data, $cloudPush = true) {
		foreach($this->pipes->getPipesByUserId($userId) as $pipe) {
			$this->sendToPipe($pipe, $data);
		}

		if ($cloudPush) {
			$this->cloudManager->push($userId, $data);
		}
	}


    /**
     * @param int $userId
     */
    public function refreshUser($userId) {
        if($this->userTimeouts[$userId] instanceof TimerInterface) {
            $this->reactLoop->cancelTimer($this->userTimeouts[$userId]);
            unset($this->userTimeouts[$userId]);
        }

        $this->userTimeouts[$userId] = $this->reactLoop->addTimer(self::INACTIVE_TIMEOUT, array($this, 'onTimeout'));
        $this->userTimeouts[$userId]->setData($userId);
    }


	// noop
	public function onCall(ConnectionInterface $conn, $id, $topic, array $params) { }
	public function onSubscribe(ConnectionInterface $conn, $topic) { }
	public function onUnSubscribe(ConnectionInterface $conn, $topic) { }
	public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) { }

}
