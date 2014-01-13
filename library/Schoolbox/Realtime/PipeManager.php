<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;


use Predis\ClientInterface as PredisClientInterface;
use Predis\CommunicationException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use React\Stomp\Factory as StompFactory;
use Schoolbox\Notif\NotifException;
use Schoolbox\Notif\Queue\UnreadMessage;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\Stomp\Subscription;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception as MapperException;

class PipeManager implements WampServerInterface, MessageComponentInterface {

	/** @var Pipe[] */
	protected $pipesBySocketKey;

	/** @var Pipe[][] */
	protected $pipesBySessionId;

	/** @var Pipe[][] */
	protected $pipesByUser;

	/** @var SubscriptionManager */
	protected $subscriptionManager;

	/** @var LoopInterface */
	protected $reactLoop;

	/** @var PredisClientInterface */
	protected $predisClient;

	/** @var \Schoolbox\UserMapper */
	protected $userMapper;

	/** @var Verboser */
	protected $verboser;


	/**
	 * @param LoopInterface $reactLoop
	 * @param PredisClientInterface $predisClient
	 * @param UserMapper $userMapper
	 * @param Verboser $verboser
	 */
	public function __construct($reactLoop, $predisClient, $userMapper, $verboser = null) {
		$this->pipes = array();
		$this->subscriptionManager = new SubscriptionManager(new StompFactory($reactLoop), $this);
		$this->reactLoop = $reactLoop;
		$this->predisClient = $predisClient;
		$this->userMapper = $userMapper;
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

		$this->v('Received socket connection');

		// receive new connection, register a pipe based on it
		$pipe = new Pipe($conn);

		try {
			$this->registerPipe($pipe);
		}
		catch(CommunicationException $e) {
			// Redis baulked: signal a retry
			$this->v($e->getMessage(), '!');

			$this->sendToPipe($pipe, json_encode(array(
				'error' => 'Service Unavailable',
				'code'  => 503,
				'retry' => true
			)));
		}
		catch(MapperException $e) {
			// user not found, not logged in: signal authentication required
			$this->v('Client not authenticated', '!');

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
		$this->v('Received socket closure');
		// TODO: Implement onClose() method.

		$socketKey = Pipe::getSocketKeyForConnection($conn);

		if(array_key_exists($socketKey, $this->pipesBySocketKey)) {
			$this->unregisterPipe($this->pipesBySocketKey[$socketKey]);
		}
		else {
			$this->v("Ignoring closure of '$socketKey'");
		}

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
		// TODO: Implement onError() method.
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

		if(!array_key_exists($socketKey, $this->pipesBySocketKey)) {
			// theoretically this is impossible...but you just never know...
			throw new ConnectionException("Unrecognised pipe: '$socketKey'");
		}

		$pipe = $this->pipesBySocketKey[$socketKey];
		$user = $pipe->getUser();
		$username = $user ? $user->getUsername() : 'unknown user';
		$sessionId = $pipe->getSession() ?: 'sessionless';

		$this->v(sprintf('Received message: %s (%s:%s) > %s', $username, $socketKey, $sessionId, $msg), '>');


		// we'll just ignore messages that are of unknown origin
		if(!$user) {
			return;
		}

		$data = json_decode($msg, true);

		// and we'll also ignore it if it doesn't parse as json in a way we expect
		if(is_null($data) || count($data) != 1) {
			return;
		}

		$request = each($data);

		$command = $request['key'];
		$arguments = $request['value'];

		$method = 'doPipe' . ucfirst($command);

		// process the command that's been sent from the client
		if(method_exists($this, $method)) {
			$this->v("Executing $method for $username [$socketKey]", '*');
			$response = $this->$method($pipe, $arguments);
			$this->sendToPipe($pipe, json_encode(array($command => $response)));
		}
		else {
			$this->sendToPipe($pipe, "Cannot understand command '$command'");
		}

	}


	/**
	 * This is the method called from within a Subscription object for notifying attached sockets of a new message.
	 *
	 * @param UnreadMessage $message
	 * @param User $user
	 */
	public function onAMQP($message, $user) {
		$this->v("Received new message to send to ".$user->getUsername());
		$this->sendToUser($user, json_encode(array(
			'push' => array(
				$message->getId() => $message->toArray()
			)
		)));
	}


	/**
	 * Register this pipe connection
	 *
	 * @param Pipe $pipe
	 * @throws \Schoolbox\Mapper\Exception
	 */
	protected function registerPipe($pipe) {
		$socketKey = $pipe->getSocketKey();
		$sessionId = $pipe->getSession();

		$this->pipesBySocketKey[$socketKey] = $pipe;
		$this->v("REGISTER   '$socketKey'", '+');

		if($sessionId) {
			$this->pipesBySessionId[$sessionId][$socketKey] = $pipe;
			$this->v("LINK       '$socketKey' -> SESSION '$sessionId'");

			if($user = $this->findUserBySession($sessionId)) {
				$pipe->setUser($user);
				$this->pipesByUser[$user->getId()][$socketKey] = $pipe;
				$this->v("LINK       '$socketKey' -> USER    '{$user->getUsername()}'");
			}
		}
		else {
			throw new MapperException("No session, no user.");
		}
	}

	/**
	 * Unregister this pipe connection
	 *
	 * @param Pipe $pipe
	 */
	protected function unregisterPipe($pipe) {

		$socketKey = $pipe->getSocketKey();
		$sessionId = $pipe->getSession();

		if($sessionId) {
			unset($this->pipesBySessionId[$sessionId][$socketKey]);
		}

		if($user = $pipe->getUser()) {
			unset($this->pipesByUser[$user->getId()][$socketKey]);
		}

		// send a connection-close to the client at the other end of the pipe
		$this->pipesBySocketKey[$socketKey]->close();

		$this->v("UNREGISTER '$socketKey'", '-');
		unset($this->pipesBySocketKey[$socketKey]);
	}

	/**
	 * Unregister all pipes associated with a session.
	 *
	 * @param string $sessionId
	 */
	protected function unregisterAllPipesBySession($sessionId) {
		if(array_key_exists($sessionId, $this->pipesBySessionId)) {
			foreach($this->pipesBySessionId[$sessionId] as $pipe) {
				$this->unregisterPipe($pipe);
			}
		}
	}


	/**
	 * Unregister all pipes associated with a user.
	 *
	 * @param User $user
	 */
	protected function unregisterAllPipesByUser($user) {
		if(array_key_exists($user->getId(), $this->pipesByUser)) {
			foreach($this->pipesByUser[$user->getId()] as $pipe) {
				$this->unregisterPipe($pipe);
			}
		}
	}


	/**
	 * Extracts a user by ID from a Redis session and returns the associated User object, or
	 * null if the user cannot be located.
	 *
	 * @param string $sessionId
	 * @return User|null
	 */
	protected function findUserBySession($sessionId) {
		if(!$sessionId) {
			return null;
		}

		$redisResponse = $this->predisClient->executeCommand(
			$this->predisClient->createCommand('get', array('PHPREDIS_SESSION:'.$sessionId))
		);

		preg_match('`id\|s:[0-9]+:"([0-9]+)";`', $redisResponse, $matches);

		// TODO triple-check validity of session. If it's invalid, please throw an exception.
		// TODO Any exception will do, but the message string will be sent to the client

		$userId = $matches[1];

		/** @var User $user */
		return $this->userMapper->findById($userId);
	}

	/**
	 * Send data to a specific pipe.
	 *
	 * @param Pipe $pipe
	 * @param string $data
	 */
	public function sendToPipe($pipe, $data) {
		$this->v("Sending '$data' to {$pipe->getSocketKey()}");
		$pipe->send($data);
	}

	/**
	 * Send data to all pipes associated with a specific session.
	 *
	 * @param string $sessionId
	 * @param string $data
	 */
	public function sendToSession($sessionId, $data) {
		if(array_key_exists($sessionId, $this->pipesBySessionId)) {
			foreach($this->pipesBySessionId[$sessionId] as $pipe) {
				$this->sendToPipe($pipe, $data);
			}
		}
	}

	/**
	 * Send data to all pipes associated with a specific user.
	 *
	 * @param User $user
	 * @param string $data
	 */
	public function sendToUser($user, $data) {
		if(array_key_exists($user->getId(), $this->pipesByUser)) {
			foreach($this->pipesByUser[$user->getId()] as $pipe) {
				$this->sendToPipe($pipe, $data);
			}
		}
	}


	/**
	 * Message from client: stub (test connectivity).
	 *
	 * @param Pipe $pipe
	 * @param mixed $arguments
	 * @return bool
	 */
	public function doPipe_($pipe, $arguments) {
		$this->v('STUB', 'd');
		return true;
	}


	/**
	 * Message from client: ensure STOMP subscription exists for user.
	 *
	 * @param Pipe $pipe
	 * @param mixed $null
	 * @return bool
	 */
	public function doPipeSubscribe($pipe, $null) {
		if($user = $pipe->getUser()) {
			$this->subscriptionManager->subscribe($user);
			// this is "you are subscribed" not "the subscription action just worked"
			return true;
		}
		return false;
	}


	/**
	 * Message from client: retrieve n messages, both read and unread.
	 *
	 * @param Pipe $pipe
	 * @param int $count
	 * @return bool
	 */
	public function doPipeFetch($pipe, $count) {
		if(($user = $pipe->getUser()) && is_numeric($count)) {

			$messages = array();

			// get a queue to retrieve read messages synchronously
			$mqf = new UserQueueFactory();
			$queue = $mqf->buildUserQueue($user);

			$allMessages = $queue->getMessages($count, true);
			$unreadMessages = $this->subscriptionManager->getUserSubscription($user, true)->getMessages();

			foreach($allMessages as $read) {
				$messages[$read->getId()] = $read->toArray();
			}

			foreach($unreadMessages as $unread) {
				$messages[$unread->getId()] = $unread->toArray();
			}

			return $messages;

		}
		return array();
	}

	/**
	 * Message from client: mark the given messages as read.
	 *
	 * @param Pipe $pipe
	 * @param array $messageIds
	 * @return bool
	 */
	public function doPipeAck($pipe, $messageIds) {
		if($user = $pipe->getUser()) {
			if($subscription = $this->subscriptionManager->getUserSubscription($user, true)) {
				$markAsRead = $subscription->acceptMessages($messageIds);
				// if there are messages to mark as read, notify all sockets attached for this user
				if(count($markAsRead)) {
					$this->sendToUser($user, json_encode(array('mark_as_read' => $markAsRead)));
				}
				return true;
			}
		}
		return false;
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