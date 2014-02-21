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
use Schoolbox\Notif\MQ\Connector;
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

	protected $terminateOnceNotified = false;

	/**
	 * @param bool $terminateOnceNotified
	 */
	public function __construct($terminateOnceNotified = false) {
		$this->terminateOnceNotified = $terminateOnceNotified;
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
		echo $string;
		if($this->terminateOnceNotified) {
			exit(0);
		}
	}

	/**
	 * Message from client: ensure STOMP subscription exists for user.
	 */
	public function subscribe() {
		// this is just a bounceback, since subscription has to happen in the parent process.
		// but rather than call in a blocking way from the server, this adds it to the event loop
		$this->notifyServer('subscribe');
	}


	/**
	 * Message from client: retrieve n messages, both read and unread.
	 *
	 * @param int $userId
	 * @param int $count
	 * @param Connector $mq
	 * @return bool
	 */
	public function fetch($userId, $count, $mq) {

		try {

			$messages = array();

			// get a queue to retrieve read messages synchronously
			$mqf = new UserQueueFactory($mq);

			$queue = $mqf->buildUserQueue($userId, $count, false);
			$allMessages = $queue->getMessages($count);

//			$unreadMessages = $this->subscriptionManager->getUserSubscription($userId, true)->getMessages();

			foreach($allMessages as $read) {
				$messages[$read->getId()] = $read->getBody();
			}

//			foreach($unreadMessages as $unread) {
//				$messages[$unread->getId()] = $unread->getBody();
//			}

			$this->notifyPipe(json_encode(array(
				'fetch' => $messages
			)));

		}
		catch(\Exception $e) {
			echo get_class($e) . " " . $e->getTraceAsString();
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