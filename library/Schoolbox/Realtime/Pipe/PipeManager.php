<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\WorkerCrew\Transaction\Transaction;
use PhpAmqpLib\Message\AMQPMessage;
use Predis\ClientInterface as PredisClientInterface;
use Predis\CommunicationException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServerInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\Socket\ConnectionException;
use React\Stomp\Factory as StompFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\ProcessException;
use Schoolbox\Realtime\SessionException;
use Schoolbox\Realtime\Stomp\SubscriptionException;
use Schoolbox\Realtime\Stomp\SubscriptionManager;
use Alaress\WorkerCrew\Dispatcher;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress\WorkerCrew\TaskResponseHandlerInterface;
use Schoolbox\Realtime\Verboser;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * This is the main "app" of our React loop.
 *
 * Class PipeManager
 * @package Schoolbox\Realtime
 */
class PipeManager implements WampServerInterface, MessageComponentInterface, TaskResponseHandlerInterface {

	const REDIS_SESSION_PREFIX = 'PHPREDIS_SESSION:';
	const PHP_SESSION_PREFIX   = 'PHPSESSID';

	/** @var PipeCollection */
	protected $pipes;

	/** @var SubscriptionManager */
	protected $subscriptionManager;

	/** @var LoopInterface */
	protected $reactLoop;

	/** @var Verboser */
	protected $verboser;

	/** @var Dispatcher */
	protected $taskDispatcher;

	/** @var Transaction[] */
	protected $pushTransactions;

	/**
	 * @param LoopInterface $reactLoop
	 * @param PredisClientInterface $predisClient
	 * @param Verboser $verboser
	 * @param DispatchStrategyInterface $strat
	 */
	public function __construct($reactLoop, $predisClient, $verboser, $strat = null) {
		$this->pipes = new PipeCollection($predisClient, $verboser);
		$this->subscriptionManager = new SubscriptionManager(new StompFactory($reactLoop), $this);
		$this->reactLoop = $reactLoop;
		$this->taskDispatcher = new Dispatcher($reactLoop, $this, $verboser, $strat);
		$this->verboser = $verboser;

		$this->v('Instantiated PipeManager');
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

		$this->v( sprintf( 'Received socket connection from %s', $pipe->getRemoteAddress()));

		try {
			$this->pipes->registerPipe($pipe);
		}
		catch(CommunicationException $e) {
			// couldn't talk to redis, send a retry back to the client
			$this->v("Communication Exception" . $e->getMessage(), '!');

			$this->sendToPipe($pipe, json_encode(array(
				'error' => 'Service Unavailable',
				'code'  => 503,
				'retry' => true
			)));
			$pipe->close();
		}
		catch(SessionException $e) {
			// user not found, not logged in: signal authentication required
			$this->v('Failed to fully register pipe: '.$e->getMessage(), '!');

			$this->sendToPipe($pipe, json_encode(array(
				'error' => 'Unauthorized',
				'code'  => 401,
			)));
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
		// explicitly close the descriptor to the temporary-file associated with the request that initiated this conn
		$conn->WebSocket->request->getBody()->close();
		$this->pipes->unregisterPipe(Pipe::getSocketKeyForConnection($conn));
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
		$this->v(sprintf('Exception! %s: "%s" - %s:%s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()), '!');

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

		$this->v(sprintf('Received message: %s (%s:%s) > %s', $userId ?: 'unknown', $socketKey, $sessionId, $msg), '>');

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

		// don't flatly spawn a new worker: add the request to a queue
		$this->taskDispatcher->enqueue($pipe, $command, $arguments);

	}


	/**
	 * This is the method called from within a Subscription object for notifying attached sockets of a new message.
	 *
	 * @param AMQPMessage $message
	 * @param int $userId
	 * @return bool
	 * @throws ProcessException
	 */
	public function onAMQP($message, $userId) {

		if(!$this->pipes->userPipeCount($userId)) {
			return false;
		}

		// just get *a* pipe for the user, it doesn't matter which
		$pipe = reset($this->pipes->getPipesByUserId($userId));
		$self = $this;

		if(empty($this->pushTransactions[$userId])) {
			$this->pushTransactions[$userId] = new Transaction($this->reactLoop,
				function($timer) use ($self, $pipe) {
					/** @var Timer $timer */
					// the timer contains the data to be processed
					$self->v("Spawning worker to push cache", '>');
					// run the instances through a decorator
					$decorator = new Decorator();
					$decorator->appendInstances($timer->getData());
					// enqueue to be dispatched
					$self->taskDispatcher->enqueue($pipe, 'push', $decorator->renderAsSocketArray(true));
				}
			);
		}

		$formatted = json_decode($message->body, true);
		$instance = Instance::jsonUnserialize($formatted['message']);
		$hash = sha1($formatted['message']);

		$this->pushTransactions[$userId]->put($hash, $instance);

		return true;
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

		$this->v("Handling a worker response: $command");

		switch(strtolower($command)) {
			// deal with this yourself
			case 'subscribe':
				try {
					if(!$this->subscriptionManager->subscribe($userId, new SelfResponder($this, $pipe))) {
						// initiate a push to the client if subscribing failed (ie. they already got one)
						$this->subscriptionManager->getUserSubscription($userId)->push();
					}
				}
				catch(SubscriptionException $e) {
					// could not subscribe the user, rabbit-offline has already been reported, just inform the user
					$this->sendToPipe($pipe, array($command => false));
				}
				break;

			// this was dealt with by a worker, just push the result
			case 'fetch':
				$data = $data[0];
				$this->sendToPipe($pipe, array($command => $data));
				break;
			case 'push':
				$this->sendToUser($userId, array($command => $data));
				break;

			// deal with this yourself
			case 'ack':
				if($subscription = $this->subscriptionManager->getUserSubscription($userId, true)) {
					$markAsRead = $subscription->acceptMessages($data[1]);
					$this->sendToUser($userId, array($command => $markAsRead));
				}
				break;
		}

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
		$this->v("Sending $bytes bytes to {$pipe->getSocketKey()}", '<');
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
	 * @param int $userId
	 * @param string $data
	 */
	public function sendToUser($userId, $data) {
		foreach($this->pipes->getPipesByUserId($userId) as $pipe) {
			$this->sendToPipe($pipe, $data);
		}
	}


	// noop
	public function onCall(ConnectionInterface $conn, $id, $topic, array $params) { }
	public function onSubscribe(ConnectionInterface $conn, $topic) { }
	public function onUnSubscribe(ConnectionInterface $conn, $topic) { }
	public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) { }


	public function v($msg, $char = ' ') {
		$this->verboser->v($msg, $char);
	}
}