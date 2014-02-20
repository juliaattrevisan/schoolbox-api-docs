<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Predis\ClientInterface as PredisClientInterface;
use Predis\CommunicationException;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Wamp\WampServerInterface;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionException;
use React\Stomp\Factory as StompFactory;
use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\NotifException;
use Schoolbox\Notif\Queue\UnreadMessage;
use Schoolbox\Realtime\RealtimeException;
use Schoolbox\Realtime\SessionException;
use Schoolbox\Realtime\Stomp\SubscriptionManager;
use Schoolbox\Realtime\Verboser;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * This is the main "app" of our React loop.
 *
 * Class PipeManager
 * @package Schoolbox\Realtime
 */
class PipeManager implements WampServerInterface, MessageComponentInterface {

	const REDIS_SESSION_PREFIX = 'PHPREDIS_SESSION:';

	/** @var PipeInterface[] */
	protected $pipesBySocketKey;

	/** @var PipeInterface[][] */
	protected $pipesBySessionId;

	/** @var PipeInterface[][] */
	protected $pipesByUser;

	/** @var SubscriptionManager */
	protected $subscriptionManager;

	/** @var LoopInterface */
	protected $reactLoop;

	/** @var PredisClientInterface */
	protected $predisClient;

	/** @var Verboser */
	protected $verboser;

	/**
	 * @param LoopInterface $reactLoop
	 * @param PredisClientInterface $predisClient
	 * @param Verboser $verboser
	 */
	public function __construct($reactLoop, $predisClient, $verboser) {
		$this->pipes = array();
		$this->subscriptionManager = new SubscriptionManager(new StompFactory($reactLoop), $this);
		$this->reactLoop = $reactLoop;
		$this->predisClient = $predisClient;
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
			$this->registerPipe($pipe);
			$this->v(sprintf("PIPEREG = %s", count($this->pipesBySocketKey)), '*');
		}
		catch(CommunicationException $e) {
			// Redis baulked: signal a retry
			$this->v($e->getMessage(), '!');

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
		$this->v('Received socket closure');

		$socketKey = Pipe::getSocketKeyForConnection($conn);

		if(array_key_exists($socketKey, $this->pipesBySocketKey)) {
			$this->unregisterPipe($this->pipesBySocketKey[$socketKey]);
		}
		else {
			$this->v("Ignoring closure of '$socketKey'");
		}

		$this->v(sprintf("PIPEREG = %s", count($this->pipesBySocketKey)), '*');

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
		$userId = $pipe->getUserId();
		$sessionId = $pipe->getSession() ?: 'sessionless';

		$this->v(sprintf('Received message: %s (%s:%s) > %s', $userId ?: 'unknown', $socketKey, $sessionId, $msg), '>');


		// we'll just ignore messages that are of unknown origin
		if(!$userId) {
			return;
		}

		$data = json_decode($msg, true);

		// and we'll also ignore it if it doesn't parse as json in a way we expect (which is key => data)
		if(is_null($data) || count($data) != 1) {
			return;
		}

		// BEWARE: THIS FORKS!
		$responder = new Responder($this->subscriptionManager);

		// as a parent, we want to let the child perform the action
		if($responder->isParentProcess()) {
			$this->attachResponder($responder, $pipe);
			return;
		}

		// YOU ARE IN CHILD CONTEXT RIGHT NOW
		unset($this->pipesBySocketKey);
		unset($this->pipesBySessionId);
		unset($this->pipesByUser);
		$this->reactLoop->stop();
		unset($this->reactLoop);
		unset($this->subscriptionManager);
		unset($this->predisClient);


		$request = each($data);

		$command = $request['key'];
		$arguments = $request['value'];

		try {
			if(method_exists($responder, $command) && in_array($command, array('subscribe', 'fetch', 'ack'))) {
				// process the command that's been sent from the client
				$this->v("Executing $command for user $userId [$socketKey]");

				// the command executed could take several seconds, depending on what it is
				 $responder->$command($userId, $arguments);
			}
		}
		catch(\Exception $e) {
			// catch any and all exceptions here, this MUST exit
			$this->v($e->getMessage(), '!');
			$notif = new Notif(Notif::TOPIC_NOTIFICATION);
			$notif->critical("Messaging error: $e");
		}

		// since we're about to terminate, we don't really care if this doesn't work,
		// it just means it was closed earlier
		@fclose($responder->getSocketStream());

		// child termination
		exit;

	}


	/**
	 * This is the method called from within a Subscription object for notifying attached sockets of a new message.
	 *
	 * @param UnreadMessage $message
	 * @param int $userId
	 */
	public function onAMQP($message, $userId) {
//		$this->v("Received new message to send to ".$user->getUsername());

		// if traffic gets high enough, we may need to look at micro-caching and batching these pushes

		$this->sendToUser($userId, json_encode(array(
			'push' => array(
				$message->getId() => $message->getBody()
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

			if($userId = $this->findUserBySession($sessionId, $pipe->getRemoteAddress())) {
				$pipe->setUserId($userId);
				$this->pipesByUser[$userId][$socketKey] = $pipe;
				$this->v("LINK       '$socketKey' -> USER    '$userId'");
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

		if($userId = $pipe->getUserId()) {
			unset($this->pipesByUser[$userId][$socketKey]);
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
	protected function unregisterAllPipesBySessionId($sessionId) {
		if(array_key_exists($sessionId, $this->pipesBySessionId)) {
			foreach($this->pipesBySessionId[$sessionId] as $pipe) {
				$this->unregisterPipe($pipe);
			}
		}
	}


	/**
	 * Unregister all pipes associated with a user.
	 *
	 * @param int $userId
	 */
	protected function unregisterAllPipesByUserId($userId) {
		if(array_key_exists($userId, $this->pipesByUser)) {
			foreach($this->pipesByUser[$userId] as $pipe) {
				$this->unregisterPipe($pipe);
			}
		}
	}


	/**
	 * Extracts a user by ID from a Redis session and returns the associated User object, or
	 * null if the user cannot be located.
	 *
	 * @param string $sessionId
	 * @param string $pipeIp
	 * @throws SessionException
	 * @return int
	 */
	protected function findUserBySession($sessionId, $pipeIp = null) {
		if(!$sessionId) {
			throw new SessionException("No sessionID provided on which to search.");
		}

		$redisResponse = $this->predisClient->executeCommand(
			$this->predisClient->createCommand('get', array(self::REDIS_SESSION_PREFIX.$sessionId))
		);

		if(!preg_match('`id\|s:[0-9]+:"([0-9]+)";`', $redisResponse, $matches)) {
			throw new SessionException("User not found");
		}

		$userId = $matches[1];

		if(is_numeric($userId)) {
			return $userId;
		}
		throw new SessionException("User ID not found in session.");
	}

	/**
	 * @param Responder $responder
	 * @param PipeInterface $pipe
	 */
	protected function attachResponder($responder, $pipe) {

		// YOU NEED TO LISTEN TO THIS
		$socketStream = $responder->getSocketStream();

		$this->v('As parent, attaching responder');

		$rSock = new \React\Stream\Stream($socketStream, $this->reactLoop);

		$buffer = '';

		$rSock->on('data', function($data, $socketStream) use (&$buffer) {
			/**
			 * @var string $data
			 * @var \React\Stream\Stream $socketStream
			 */

			if($data) {
				$buffer .= $data;
			}

		});

		$pipeManager = $this;
		$subscriptionManager = $this->subscriptionManager;

		$rSock->on('close', function($stream) use(&$buffer, $pipeManager, $pipe, $responder, $subscriptionManager) {
			// received EOF, time to send the result to the client

			// don't attempt to split if the data came through badly
			if(strpos($buffer, Responder::CONTEXT_DELIMITER) === false) {
				return;
			}

			list($context, $data) = explode(Responder::CONTEXT_DELIMITER, $buffer, 2);

			// validate data given for debugging
			if(is_null(json_decode($data, true))) {
				$pipeManager->v("Data piped is not JSON: $data", '?');
			}

			// depending on what context was supplied, send the data to the appropriate pipes
			switch($context) {
				case Responder::CONTEXT_PIPE:
					$pipeManager->sendToPipe($pipe, $data);
					break;
				case Responder::CONTEXT_USER:
					$pipeManager->sendToUser($pipe->getUserId(), $data);
					break;
				case Responder::CONTEXT_SESSION:
					$pipeManager->sendToSession($pipe->getSession(), $data);
					break;
				case Responder::CONTEXT_SELF:
					// this is a bounceback so the subscription is registered in the parent
					if($data == 'subscribe') {
						$subscriptionManager->subscribe($pipe->getUserId(), new SelfResponder($pipeManager, $pipe));
						break;
					}
					$data = json_decode($data, true);
					// this is also a bounceback, because you can only ack from the subscription you started
					if(isset($data['ack'])) {
						if($subscription = $subscriptionManager->getUserSubscription($pipe->getUserId(), true)) {
							$markAsRead = $subscription->acceptMessages($data['ack']);
							$pipeManager->sendToUser($pipe->getUserId(), json_encode(array('ack' => $markAsRead)));
						}
					}
					break;
			}
		});


	}


	/**
	 * Send data to a specific pipe.
	 *
	 * @param Pipe $pipe
	 * @param string $data
	 */
	public function sendToPipe($pipe, $data) {
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
		if(array_key_exists($sessionId, $this->pipesBySessionId)) {
			foreach($this->pipesBySessionId[$sessionId] as $pipe) {
				$this->sendToPipe($pipe, $data);
			}
		}
	}

	/**
	 * Send data to all pipes associated with a specific user.
	 *
	 * @param int $userId
	 * @param string $data
	 */
	public function sendToUser($userId, $data) {
		if(array_key_exists($userId, $this->pipesByUser)) {
			foreach($this->pipesByUser[$userId] as $pipe) {
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

	// noop
	public function onCall(ConnectionInterface $conn, $id, $topic, array $params) { }
	public function onSubscribe(ConnectionInterface $conn, $topic) { }
	public function onUnSubscribe(ConnectionInterface $conn, $topic) { }
	public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) { }


	public function v($msg, $char = ' ') {
		$this->verboser->v($msg, $char);
	}
}