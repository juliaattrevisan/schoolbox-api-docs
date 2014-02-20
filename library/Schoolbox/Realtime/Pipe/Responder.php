<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Pipe;


use Evenement\EventEmitter;
use React\Stomp\Client;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\ProcessException;
use Schoolbox\Realtime\Stomp\SubscriptionManager;

class Responder extends EventEmitter {

	const TIMEOUT_SUBSCRIBE = 3;
	const TIMEOUT_FETCH = 10;

	const CONTEXT_USER = 'user';
	const CONTEXT_SESSION = 'session';
	const CONTEXT_PIPE = 'pipe';
	const CONTEXT_SELF = 'self';
	const CONTEXT_DELIMITER = "\037"; // nonprintable unit separator character

	const STREAM_BUFFER = 512;

	/** @var SubscriptionManager */
	protected $subscriptionManager;

	protected $socketStream;

	protected $pid = null;

	/**
	 * @param SubscriptionManager $subscriptionManager
	 * @throws \Schoolbox\Realtime\ProcessException
	 */
	public function __construct($subscriptionManager) {
		$this->subscriptionManager = $subscriptionManager;
		// we're about to fork, so we need a way to maintain communication with our parent
		list($parent, $child) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

		// spin off a new process: you'd think forking inside a constructor might not work? totally does
		$this->pid = pcntl_fork();

		switch($this->pid) {
			case -1: // fail
				throw new ProcessException("Could not fork!");
			case 0:  // child
				fclose($child);
				stream_set_blocking($parent, 0);
				$this->socketStream = $parent;
				break;
			default: //parent
				fclose($parent);
				stream_set_blocking($child, 0);
				$this->socketStream = $child;
				break;
		}
	}

	/**
	 * @return bool
	 */
	public function isChildProcess() {
		return $this->pid === 0;
	}

	/**
	 * @return bool
	 */
	public function isParentProcess() {
		return !$this->isChildProcess();
	}

	public function getPID() {
		return $this->pid;
	}

	/**
	 * @param $data
	 */
	public function notifyServer($data) {
		$this->notify(self::CONTEXT_SELF . self::CONTEXT_DELIMITER . $data);
	}

	/**
	 * @param $data
	 */
	public function notifyPipe($data) {
		$this->notify(self::CONTEXT_PIPE . self::CONTEXT_DELIMITER . $data);
	}

	/**
	 * @param $data
	 */
	public function notifySession($data) {
		$this->notify(self::CONTEXT_SESSION . self::CONTEXT_DELIMITER . $data);
	}

	/**
	 * @param $data
	 */
	public function notifyUser($data) {
		$this->notify(self::CONTEXT_USER . self::CONTEXT_DELIMITER . $data);
	}

	protected function notify($string) {
		$close = true;
		for($written = 0; $written < strlen($string); $written += $fwrite) {
			$fwrite = fwrite($this->socketStream, substr($string, $written), self::STREAM_BUFFER);
			// we're finished early
			if($fwrite === false) {
				break;
			}
			// call a plumber: busted pipe
			elseif($fwrite === 0) {
				$close = false;
				break;
			}
		}

		if($close) {
			fclose($this->socketStream);
		}
	}

	public function getSocketStream() {
		return $this->socketStream;
	}

	/**
	 * Message from client: ensure STOMP subscription exists for user.
	 *
	 * @param int $userId
	 * @param mixed $null
	 * @return null
	 */
	public function subscribe($userId, $null) {
		// this is just a bounceback, since subscription has to happen in the parent process.
		// but rather than call in a blocking way from the server, this adds it to the event loop
		$this->notifyServer('subscribe');
	}


	/**
	 * Message from client: retrieve n messages, both read and unread.
	 *
	 * @param int $userId
	 * @param int $count
	 * @return bool
	 */
	public function fetch($userId, $count) {

		try {



		$messages = array();

		// get a queue to retrieve read messages synchronously
		$mqf = new UserQueueFactory();
		$queue = $mqf->buildUserQueue($userId, $count, false);

		$allMessages = $queue->getMessages($count);
		$unreadMessages = $this->subscriptionManager->getUserSubscription($userId, true)->getMessages();

		foreach($allMessages as $read) {
			$messages[$read->getId()] = $read->getBody();
		}

		foreach($unreadMessages as $unread) {
			$messages[$unread->getId()] = $unread->getBody();
		}

		$this->notifyPipe(json_encode(array(
			'fetch' => $messages
		)));

		}
		catch(\Exception $e) {
			echo $e->getTraceAsString();
		}
	}

	/**
	 * Message from client: mark the given messages as read.
	 *
	 * @param int $userId
	 * @param array $messageIds
	 * @return bool
	 */
	public function ack($userId, $messageIds) {
		$this->notifyServer(json_encode(array('ack' => $messageIds)));
	}



}