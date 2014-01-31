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

	const QUEUE_ALL_MESSAGES = 'u.%s.all';
	const QUEUE_UNREAD_MESSAGES = 'u.%s.unread';

	/** @var AbstractMessage[] */
	protected $messages = array();

	/** @var MQConnector */
	protected $conn;

	/** @var string */
	protected $username;

	/** @var array */
	protected $bindings;

	/**
	 * @param array $bindings
	 * @param MQConnector $mqConnector
	 */
	public function __construct($bindings, $mqConnector, $simulate = false) {
		$this->conn = $mqConnector;
		$this->username = $bindings['user']['username'];
		$userId = $bindings['user']['id'];

		unset($bindings['user']);

		// the user's personal routing key
		$this->bindings = array("*.notify.user.$userId");

		// all remaining bindings specified
		foreach($bindings as $type => $binds) {
			foreach($binds as $id) {
				$this->bindings[] = "*.notify.$type.$id";
			}
		}

		if($simulate) {
			return;
		}

		$exchange = MQConnector::SECONDARY_NAME_PREFIX . $this->username;

		// one connection to declare them all...
		$this->conn->declareExchange($exchange);
		$this->conn->declareQueue(sprintf(self::QUEUE_UNREAD_MESSAGES, $this->username));
		$this->conn->declareQueue(sprintf(self::QUEUE_ALL_MESSAGES, $this->username));

		// ...and in the darkness bind them.
		foreach($this->bindings as $key) {
			$this->conn->bindExchange($exchange, $key);
		}
		$this->conn->bindQueue(sprintf(self::QUEUE_UNREAD_MESSAGES, $this->username), '', $exchange);
		$this->conn->bindQueue(sprintf(self::QUEUE_ALL_MESSAGES, $this->username),   '', $exchange);

	}

	public function getBindings() {
		return $this->bindings;
	}


	/**
	 * Retrieve messages from the queue
	 * @param int $limit - how many messages to retrieve, from most recent backward
	 * @param bool $forceRefresh - whether or not the list of messages should be repopulated
	 * @param bool $preserveKeys - whether or not to keep the message ID as the array key
	 * @return ReadMessage[]
	 */
	public function getMessages($limit = 10, $forceRefresh = false, $preserveKeys = false) {

		// retrieve messages and sort
		if(!count($this->messages) || $forceRefresh) {
			$this->retrieveMessages();
		}

		return array_slice($this->messages, 0, $limit, $preserveKeys);
	}


	/**
	 * Populate this user's notification list (idempotent)
	 */
	protected function retrieveMessages() {

		// this method replaces the entire array with the entire set of unexpired messages
		$this->messages = array();

		$nacks = array();

		while($m = $this->conn->readFromQueue(sprintf(self::QUEUE_ALL_MESSAGES, $this->username))) {
			$msg = new ReadMessage($m);
			if(!array_key_exists($msg->getId(), $this->messages)) {
				$this->messages[$msg->getId()] = $msg;
				$nacks[] = $msg->getAmqpMessage();
			}
		}

		// sort descending
		usort($this->messages, function($a, $b) {
			$at = $a->getTimestamp();
			$bt = $b->getTimestamp();
			return $at == $bt ? 0 : $at > $bt ? -1 : 1;
		});

		// now we've collected messages, reject all
		$this->conn->rejectMessages($nacks);

	}

}