<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Queue\QueueInterface;
use OutOfBoundsException;

/**
 * This class abstracts several AMQP queues, one for each of 'digest', 'listed', 'unread' and 'to be sorted'.
 *
 * Class UserQueue
 * @package Schoolbox\Notif\Queue
 */
class UserQueue {

    // queue of messages that have been accepted by broadcast, but need to be filtered onto the other queues
    const Q_TOSORT = 'tosort';
    // queue of messages that have been received by Instant but have not yet expired
    const Q_LISTED = 'listed';
    // queue of messages that have been received by Instant and have not yet been read
    const Q_UNREAD = 'unread';
    // queue of messages that are pending to be sent by Digest
    const Q_DIGEST = 'digest';

	// queue of messages that have been accepted by broadcast, but need to be filtered onto the other queues
	const QUEUE_TOSORT_MESSAGES = '%s.u.%s.tosort';

	// queue of messages that have been received by Instant but have not yet expired
	const QUEUE_LISTED_MESSAGES = '%s.u.%s.listed';

	// queue of messages that have been received by Instant and have not yet been read
	const QUEUE_UNREAD_MESSAGES = '%s.u.%s.unread';

	// queue of messages that are pending to be sent by Digest
	const QUEUE_DIGEST_MESSAGES = '%s.u.%s.digest';

	/** @var array */
	protected $notifications = array();

    /** @var QueueInterface[] */
    protected $queues;


    /**
     * @param QueueInterface[] $userQueues
     */
    public function __construct(array $userQueues = array())
    {
        foreach ($userQueues as $q) {
            $this->addQueue($q);
        }
    }


    /**
     * @param QueueInterface $queue
     */
    public function addQueue(QueueInterface $queue)
    {
        $this->queues[$queue->getName()] = $queue;
    }


    /**
     * @return array
     */
    public function getQueueNames()
    {
        return array_keys($this->queues);
    }


    /**
     * @param string $queueName
     * @param int    $limit
     * @param bool   $refresh
     * @return array
     * @throws OutOfBoundsException
     */
    public function fetchNotifications($queueName, $limit = 10, $refresh = false)
    {
        if (empty($this->queues[$queueName])) {
            throw new OutOfBoundsException("Queue '$queueName' not declared");
        }

        if (empty($this->notifications[$queueName]) || $refresh) {
            $this->fetchFromSource($this->queues[$queueName]);
        }

        if ($limit) {
            return array_slice($this->notifications[$queueName], 0, $limit);
        }

        return $this->notifications[$queueName];
    }


    /**
     * @param QueueInterface $queue
     */
    protected function fetchFromSource(QueueInterface $queue) {
        $bundle = new NotificationBundle();

        $bundle->appendInstances($queue->fetch());
        $this->notifications[$queue->getName()] = $bundle->getAll();

        // sort descending
        uasort($this->notifications[$queue->getName()], function($a, $b) {
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
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance) {
        // only check the redis queues
        $qs = array_filter($this->queues, function ($v) {
//            return $v instanceof RedisQueue;
        });

        foreach($qs as $q) {
            /** @var QueueInterface $q */
            if($q->contains($instance)) {
                return true;
            }
        }

        return false;
    }


	/**
	 * Helper method to construct a queuename based on a pattern
	 *
     * @deprecated static calls to be removed
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
     * @deprecated static calls to be removed
	 * @param $userId
	 * @return string
	 */
	public static function getUserToSortQueueName($userId) {
		return self::getQueueName(self::QUEUE_TOSORT_MESSAGES, $userId);
	}


	/**
	 * Returns the 'listed' queuename for the given user
	 *
     * @deprecated static calls to be removed
	 * @param $userId
	 * @return string
	 */
	public static function getUserListedQueueName($userId) {
		return self::getQueueName(self::QUEUE_LISTED_MESSAGES, $userId);
	}


	/**
	 * Returns the 'unread' queuename for the given user
	 *
     * @deprecated static calls to be removed
	 * @param $userId
	 * @return string
	 */
	public static function getUserUnreadQueueName($userId) {
		return self::getQueueName(self::QUEUE_UNREAD_MESSAGES, $userId);
	}


	/**
	 * Returns the 'digest' queuename for the given user
	 *
     * @deprecated static calls to be removed
	 * @param $userId
	 * @return string
	 */
	public static function getUserDigestQueueName($userId) {
		return self::getQueueName(self::QUEUE_DIGEST_MESSAGES, $userId);
	}

}
