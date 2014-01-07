<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\User;

/**
 * This class abstracts two AMQP queues, one each for read and unread.
 *
 * Class UserQueue
 * @package Schoolbox\Notif\Queue
 */
class UserQueue {

	/** @var AbstractMessage[] */
	protected $messages = array();

	/** @var MQConnector */
	protected $conn;

	/** @var string */
	protected $queuePrefix;

	/**
	 * @param array $bindings
	 * @param MQConnector $mqConnector
	 */
	public function __construct($bindings, $mqConnector) {
		$this->conn = $mqConnector;
		$username = $bindings['user']['username'];
		$userId = $bindings['user']['id'];

		unset($bindings['user']);

		$exchange = MQConnector::SECONDARY_NAME_PREFIX . $username;

		$this->queuePrefix = "u.$username.";

		// one connection to declare them all...
		$this->conn->declareExchange($exchange);
		$this->conn->declareQueue($this->queuePrefix . "unread");
		$this->conn->declareQueue($this->queuePrefix . "read");

		// the user's personal routing key
		$keys = array("*.notify.user.$userId");

		// all remaining bindings specified
		foreach($bindings as $type => $binds) {
			foreach($binds as $id) {
				$keys[] = "*.notify.$type.$id";
			}
		}

		// ...and in the darkness bind them.
		foreach($keys as $key) {
			$this->conn->bindExchange($exchange, $key);
		}
		$this->conn->bindQueue($this->queuePrefix . "unread", '', $exchange);
		$this->conn->bindQueue($this->queuePrefix . "read",   '', $exchange);

	}


	/**
	 * Retrieve messages from the queue
	 * @param int $limit - how many messages to retrieve, from most recent backward
	 * @param bool $markAsRead - whether or not the retrieved messages should be marked read
	 * @param bool $forceRefresh - whether or not the list of messages should be repopulated
	 * @return AbstractMessage[]
	 */
	public function getMessages($limit = 10, $markAsRead = true, $forceRefresh = false) {

		// retrieve messages and sort
		if(!count($this->messages) || $forceRefresh) {
			$this->retrieveMessages($markAsRead ? $limit : 0);
		}

		return array_slice($this->messages, 0, $limit, false);
	}


	/**
	 * Populate this user's notification list
	 */
	protected function retrieveMessages($markAsReadCount) {

		while($m = $this->conn->readFromQueue($this->queuePrefix . "unread")) {
			$msg = new UnreadMessage($m);
			$this->messages[$msg->getId()] = $msg;
		}

		// hold onto these ones
		while($m = $this->conn->readFromQueue($this->queuePrefix . "read")) {
			$msg = new ReadMessage($m);
			if(!array_key_exists($msg->getId(), $this->messages)) {
				$this->messages[$msg->getId()] = $msg;
			}
		}

		// sort descending
		usort($this->messages, function($a, $b) {
			$at = $a->getTimestamp();
			$bt = $b->getTimestamp();
			return $at == $bt ? 0 : $at > $bt ? -1 : 1;
		});

		$count = count($this->messages);

		$acks  = array();
		$nacks = array();

		// iterate and flag appropriate messages as read
		for($i = 0; $i < $count; ++$i) {
			// if within count, and unread, ACCEPT this message (dequeuing it)
			if($i < $markAsReadCount && $this->messages[$i] instanceof UnreadMessage) {
				$acks[] = $this->messages[$i]->getAmqpMessage();
			}
			// if not within count, or is read, REJECT this message (so as not to dequeue it)
			else {
				$nacks[] = $this->messages[$i]->getAmqpMessage();
			}
		}

		// reject the messages in the read queue and requeue them.
		$this->conn->acceptMessages($acks);
		$this->conn->rejectMessages($nacks);



	}

}