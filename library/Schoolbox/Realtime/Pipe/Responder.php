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
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\Promise\Deferred;
use React\Socket\ConnectionException;
use React\Stomp\Client;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\Realtime\ProcessException;
use Schoolbox\Realtime\Stomp\SubscriptionManager;
use Schoolbox\User;

class Responder extends EventEmitter {

	const TIMEOUT_SUBSCRIBE = 3;
	const TIMEOUT_FETCH = 10;

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

	public function notify($string) {
		echo "[ ] NOTIFY\n";
		fwrite($this->socketStream, $string);
	}

	public function getSocketStream() {
		return $this->socketStream;
	}

	/**
	 * Message from client: ensure STOMP subscription exists for user.
	 *
	 * @param User $user
	 * @param mixed $null
	 * @return null
	 */
	public function subscribe($user, $null) {
		echo "[f] Foo.";

		for($i = 0; $i < 2; ++$i) {
			sleep(1);
			echo ".";
			$this->notify("Sleep #$i done. ");
		}

		echo "\n[b] Bar.\n";

		$this->subscriptionManager->subscribe($user, $this);
	}


	/**
	 * Message from client: retrieve n messages, both read and unread.
	 *
	 * @param User $user
	 * @param int $count
	 * @return bool
	 */
	public function fetch($user, $count) {

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

	/**
	 * Message from client: mark the given messages as read.
	 *
	 * @param User $user
	 * @param array $messageIds
	 * @return bool
	 */
	public function ack($user, $messageIds) {
		if($subscription = $this->subscriptionManager->getUserSubscription($user, true)) {
			$markAsRead = $subscription->acceptMessages($messageIds);
			// if there are messages to mark as read, notify all sockets attached for this user
//				if(count($markAsRead)) {
//					$this->sendToUser($user, json_encode(array('mark_as_read' => $markAsRead)));
//				}
			return true;
		}
		return false;
	}









	/**
	 * Use promises to perform an async task.
	 *
	 * @param string $on
	 * @param int $timeout
	 * @param callable $taskCallback
	 * @param callable $failureCallback
	 * @return \React\Promise\DeferredPromise
	 */
	protected function defer($on, $timeout, $taskCallback, $failureCallback) {
		if($this->deferrals[$on]) {
			return $this->deferrals[$on]->promise();
		}

		$promise = new Deferred();
		$responder = $this;

		$this->deferrals[$on] = $promise;
		$this->deferrals[$on]->then($taskCallback);

		$timer = $this->reactLoop->addTimer($timeout, function() use ($promise, $on, $responder) {
			$promise->reject(new ConnectionException('Connection Timeout'));
			$responder->dismiss($on);
		});

		$this->on($on, function($responder) use ($timer, $promise) {
			/**
			 * @var Timer $timer
			 * @var Deferred $promise
			 */
			$timer->cancel();
			$promise->resolve($responder);
		});

		return $this->deferrals[$on]->promise();
	}


	public function dismiss($on) {
		unset($this->deferrals[$on]);
	}

}