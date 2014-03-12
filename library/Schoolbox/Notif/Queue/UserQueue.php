<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Schoolbox\Notif\Message\Interceptor;
use Schoolbox\Notif\MQ\Connector as MQConnector;

/**
 * This class abstracts two AMQP queues, one each for read and unread.
 *
 * Class UserQueue
 * @package Schoolbox\Notif\Queue
 */
class UserQueue {

	const QUEUE_ALL_MESSAGES = '%s.u.%s.all';
	const QUEUE_UNREAD_MESSAGES = '%s.u.%s.unread';

	/** @var AbstractMessage[] */
	protected $messages = array();

	/** @var MQConnector */
	protected $conn;

	/** @var array */
	protected $bindings;

	/** @var int */
	protected $userId;

	/** @var Interceptor */
	protected $interceptor;


	/**
	 * @param array $bindings
	 * @param MQConnector $mqConnector
	 * @param bool $simulate
	 */
	public function __construct($bindings, $mqConnector, $simulate = false) {
		$this->conn = $mqConnector;
		$this->userId = $bindings['user']['id'];
		$this->interceptor = new Interceptor($this->conn);

		unset($bindings['user']);

		// the user's personal routing key
		$this->bindings = array("*.notify.user.$this->userId");

		// all remaining bindings specified
		foreach($bindings as $type => $binds) {
			foreach($binds as $id) {
				$this->bindings[] = "*.notify.$type.$id";
			}
		}

		if($simulate) {
			return;
		}

		$exchange = MQConnector::getSecondaryExchangePrefix() . $this->userId;

		// one connection to declare them all...
		$this->conn->declareExchange($exchange);
		$this->conn->declareQueue(sprintf(self::QUEUE_UNREAD_MESSAGES, INSTANCE_ID, $this->userId));
		$this->conn->declareQueue(sprintf(self::QUEUE_ALL_MESSAGES, INSTANCE_ID, $this->userId));

		// ...and in the darkness bind them.
		foreach($this->bindings as $key) {
			$this->conn->bindExchange($exchange, $key);
		}
		$this->conn->bindQueue(sprintf(self::QUEUE_UNREAD_MESSAGES, INSTANCE_ID, $this->userId), '', $exchange);
		$this->conn->bindQueue(sprintf(self::QUEUE_ALL_MESSAGES, INSTANCE_ID, $this->userId),   '', $exchange);

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

		if($limit) {
			return array_slice($this->messages, 0, $limit, $preserveKeys);
		}
		return $this->messages;

	}


	/**
	 * Populate this user's notification list (idempotent)
	 */
	protected function retrieveMessages() {

		// this method replaces the entire array with the entire set of unexpired messages
		$this->messages = array();

		$nacks = array();

		while($m = $this->conn->readFromQueue(sprintf(self::QUEUE_ALL_MESSAGES, INSTANCE_ID, $this->userId))) {

			// dismiss messages that aren't meant to remain in the user's 'all' queue
			if($this->interceptor->hasReroute($this->userId, $m)) {
				// we accept (but don't *perform* a reroute) because the '.unread' queue will handle the resend
				$this->conn->acceptMessage($m);
				continue;
			}

			$nacks[] = $m;

			// unwrap the serialisation
			$body = $this->interceptor->unwrapMessageBody($m);
			$m->setBody((string)$body['message']);

			$msg = new ReadMessage($m);
			$this->messages[$msg->getId()] = $msg;
		}

		// now we've collected messages, reject all
		$this->conn->rejectMessages($nacks);

		// sort descending
		usort($this->messages, function($a, $b) {
			$at = $a->getTimestamp();
			$bt = $b->getTimestamp();
			return $at == $bt ? 0 : $at > $bt ? -1 : 1;
		});

	}


}