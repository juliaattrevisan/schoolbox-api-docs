<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\NotificationBundle;
use Exception;
use InvalidArgumentException;
use Schoolbox\Notif\Message\External\TargetedConverter;

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

	/** @var Notification[] */
	protected $notifications = array();

	/** @var AMQPInterface */
	protected $conn;

	/** @var array */
	protected $bindings;

	/** @var int */
	protected $userId;


	/**
	 * @param array $bindings
	 * @param AMQPInterface $mqConnector
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
	 *
	 * @deprecated use getNotifications() instead
	 * @param int $limit - how many messages to retrieve, from most recent backward
	 * @param bool $forceRefresh - whether or not the list of messages should be repopulated
	 * @param bool $preserveKeys - whether or not to keep the message ID as the array key
	 * @return Notification[]
	 */
	public function getMessages($limit = 10, $forceRefresh = false, $preserveKeys = false) {
		return $this->getNotifications($limit, $forceRefresh, $preserveKeys);
	}


	/**
	 * Collate notifications from the messages in the queue
	 *
	 * @param int $limit - how many messages to retrieve, from most recent backward
	 * @param bool $forceRefresh - whether or not the list of messages should be repopulated
	 * @param bool $preserveKeys - whether or not to keep the message ID as the array key
	 * @return Notification[]
	 */
	public function getNotifications($limit = 10, $forceRefresh = false, $preserveKeys = false) {

		// retrieve messages and sort
		if(!count($this->notifications) || $forceRefresh) {
			$this->retrieveNotifications();
		}

		if($limit) {
			return array_slice($this->notifications, 0, $limit, $preserveKeys);
		}
		return $this->notifications;
	}


	/**
	 * Populate this user's notification list (idempotent)
	 */
	protected function retrieveNotifications() {
		$nacks = array();
		$bundle = new NotificationBundle();

		try {
			while($m = $this->conn->readFromQueue($this->getListedQueueName())) {
				$nacks[] = $m;

				// unwrap the formatting
				$body = json_decode($m->body, true);

				try {
					// attempt to unserialize the message
					$bundle->appendInstance(Instance::jsonUnserialize($body['message']));
				}
				catch(InvalidArgumentException $e) {
					// TODO: once TargetedMessage is fully deprecated, this catch can be removed
					$conv = new TargetedConverter();
					try {
						$bundle->appendInstance($conv->convert($body['message']));
					}
					catch(InvalidArgumentException $e) {
						// nothing we can do, message was unparseable
					}
				}
			}
		}
		catch(Exception $e) {
			// regardless of what happens above, it's imperative that we reject the messages we pulled
			$this->conn->rejectMessages($nacks);
			// *now* you can freak out
			throw $e;
		}

		// now we've collected messages, reject all
		$this->conn->rejectMessages($nacks);

		$this->notifications = $bundle->getAll();

		// sort descending
		uasort($this->notifications, function($a, $b) {
            /**
             * @var Notification $a
             * @var Notification $b
             */
            $at = $a->getTimestamp();
			$bt = $b->getTimestamp();
			return $at == $bt ? 0 : $at > $bt ? -1 : 1;
		});

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