<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Alaress\Async\AMQP\AMQPExchange;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\MQ\Connector as MQConnector;

/**
 * This class abstracts several AMQP queues, one for each of 'digest', 'listed', 'unread' and 'to be sorted'.
 *
 * Class UserQueue
 * @package Schoolbox\Notif\Queue
 */
class UserQueue {

	// queue of messages that have been accepted by broadcast, but need to be filtered onto the other queues
	const QUEUE_TOSORT_MESSAGES = '%s.u.%s.tosort';

	// queue of messages that have been received by Instant but have not yet expired
	const QUEUE_LISTED_MESSAGES = '%s.u.%s.listed';

	// queue of messages that have been received by Instant and have not yet been read
	const QUEUE_UNREAD_MESSAGES = '%s.u.%s.unread';

	// queue of messages that are pending to be sent by Digest
	const QUEUE_DIGEST_MESSAGES = '%s.u.%s.digest';

	/** @var TargetedMessage[] */
	protected $messages = array();

	/** @var MQConnector */
	protected $conn;

	/** @var array */
	protected $bindings;

	/** @var int */
	protected $userId;


	/**
	 * @param array $bindings
	 * @param MQConnector $mqConnector
	 * @param bool $simulate
	 */
	public function __construct($bindings, $mqConnector, $simulate = false) {
		$this->conn = $mqConnector;
		$this->userId = $bindings['user']['id'];

		unset($bindings['user']);

		// the user's personal routing key
		$this->bindings = array("*.notify.user.$this->userId.#");

		// all remaining bindings specified
		foreach($bindings as $type => $binds) {
			foreach($binds as $id) {
				$this->bindings[] = "*.notify.$type.$id";
			}
		}

		if($simulate) {
			return;
		}

		// one connection to declare them all...
		$exchange = $this->conn->getSecondaryExchange($this->userId);
		$this->conn->declareExchange($exchange);
		$exchangeName = $exchange->getName();

		$this->conn->declareQueue($this->getToSortQueueName());
		$this->conn->declareQueue($this->getDigestQueueName());
		$this->conn->declareQueue($this->getListedQueueName());
		$this->conn->declareQueue($this->getUnreadQueueName());

		// ...and in the darkness bind them.
		foreach($this->bindings as $key) {
			$this->conn->bindExchange($exchangeName, $key);
		}
		$this->conn->bindExchange($exchangeName, "*.digest.user.$this->userId.#");
		$this->conn->bindExchange($exchangeName, "*.instant.user.$this->userId.#");

		$this->conn->bindQueue($this->getToSortQueueName(), '*.notify.#', $exchangeName);
		$this->conn->bindQueue($this->getDigestQueueName(), "*.digest.user.$this->userId.#", $exchangeName);
		$this->conn->bindQueue($this->getListedQueueName(), "*.instant.user.$this->userId.#", $exchangeName);
		$this->conn->bindQueue($this->getUnreadQueueName(), "*.instant.user.$this->userId.#", $exchangeName);

	}


	/**
	 * @return array
	 */
	public function getBindings() {
		return $this->bindings;
	}


	/**
	 * Retrieve messages from the queue
	 * @param int $limit - how many messages to retrieve, from most recent backward
	 * @param bool $forceRefresh - whether or not the list of messages should be repopulated
	 * @param bool $preserveKeys - whether or not to keep the message ID as the array key
	 * @return TargetedMessage[]
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

		while($m = $this->conn->readFromQueue($this->getListedQueueName())) {
			$nacks[] = $m;

            $msgId = $m->get('message_id');

			// unwrap the formatting
			$body = json_decode($m->body, true);

            // insert the unserialized TargetedMessage into our list
			$this->messages[$msgId] = unserialize($body['message']);
		}

		// now we've collected messages, reject all
		$this->conn->rejectMessages($nacks);

		// sort descending
		uasort($this->messages, function($a, $b) {
            /**
             * @var TargetedMessage $a
             * @var TargetedMessage $b
             */
            $at = $a->getTimestamp();
			$bt = $b->getTimestamp();
			return $at == $bt ? 0 : $at > $bt ? -1 : 1;
		});

	}


	/**
	 * Returns the exchange that applies to this user
	 *
	 * @deprecated
	 * @return string
	 */
	public function getExchangeName() {
		return MQConnector::getSecondaryExchangePrefix() . $this->userId;
	}


	/**
	 * Helper method to construct a queuename based on a pattern
	 *
	 * @param $pattern
	 * @param $userId
	 * @return string
	 */
	protected static function getQueueName($pattern, $userId) {
		return sprintf($pattern, INSTANCE_ID, $userId);
	}


	/**
	 * Returns the 'to be sorted' queuename for the given user
	 *
	 * @param $userId
	 * @return string
	 */
	public static function getUserToSortQueueName($userId) {
		return self::getQueueName(self::QUEUE_TOSORT_MESSAGES, $userId);
	}


	/**
	 * Returns the 'listed' queuename for the given user
	 *
	 * @param $userId
	 * @return string
	 */
	public static function getUserListedQueueName($userId) {
		return self::getQueueName(self::QUEUE_LISTED_MESSAGES, $userId);
	}


	/**
	 * Returns the 'unread' queuename for the given user
	 *
	 * @param $userId
	 * @return string
	 */
	public static function getUserUnreadQueueName($userId) {
		return self::getQueueName(self::QUEUE_UNREAD_MESSAGES, $userId);
	}


	/**
	 * Returns the 'digest' queuename for the given user
	 *
	 * @param $userId
	 * @return string
	 */
	public static function getUserDigestQueueName($userId) {
		return self::getQueueName(self::QUEUE_DIGEST_MESSAGES, $userId);
	}


	/**
	 * Returns the 'to be sorted' queuename for this UserQueue
	 *
	 * @return string
	 */
	public function getToSortQueueName() {
		return self::getQueueName(self::QUEUE_TOSORT_MESSAGES, $this->userId);
	}


	/**
	 * Returns the 'listed' queuename for this UserQueue
	 *
	 * @return string
	 */
	public function getListedQueueName() {
		return self::getQueueName(self::QUEUE_LISTED_MESSAGES, $this->userId);
	}


	/**
	 * Returns the 'unread' queuename for this UserQueue
	 *
	 * @return string
	 */
	public function getUnreadQueueName() {
		return self::getQueueName(self::QUEUE_UNREAD_MESSAGES, $this->userId);
	}


	/**
	 * Returns the 'digest' queuename for this UserQueue
	 *
	 * @return string
	 */
	public function getDigestQueueName() {
		return self::getQueueName(self::QUEUE_DIGEST_MESSAGES, $this->userId);
	}

}