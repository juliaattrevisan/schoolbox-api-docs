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

    /** @var int */
    protected $userId;

    /** @var array */
    protected $instanceCache = array();

    /** @var QueueInterface[] */
    protected $queues;


    /**
     * @param int              $userId
     * @param QueueInterface[] $userQueues
     */
    public function __construct($userId, array $userQueues = array())
    {
        $this->userId = $userId;
        foreach ($userQueues as $q) {
            $this->addQueue($q);
        }
    }


    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }


    /**
     * @param QueueInterface $queue
     */
    public function addQueue(QueueInterface $queue)
    {
        $this->queues[$queue->getName()] = $queue;
    }


    /**
     * @param string $label
     * @throws OutOfBoundsException
     */
    public function removeQueue($label)
    {
        $queueName = $this->findQueue($label);
        unset(
            $this->queues[$queueName],
            $this->instanceCache[$queueName]
        );
    }


    /**
     * @return array
     */
    public function getQueueNames()
    {
        return array_keys($this->queues);
    }


    /**
     * @param $label
     * @return string
     * @throws OutOfBoundsException
     */
    public function findQueue($label)
    {
        // finds queues that end in the given label
        $matchedNames = preg_grep("`$label$`", array_keys($this->queues));

        if (empty($matchedNames)) {
            throw new OutOfBoundsException("No queue matched label '$label', did you declare it?");
        }

        if (count($matchedNames) != 1) {
            throw new OutOfBoundsException("Multiple queues matched label '$label', try being more specific");
        }

        return $matchedNames[0];
    }


    /**
     * @param string $label
     */
    public function drain($label)
    {
        $name = $this->findQueue($label);
        $q = $this->queues[$name];

        $q->drain();
    }

    /**
     * @param string $label
     * @param int    $fetch
     * @param bool   $refresh
     * @return array
     * @throws OutOfBoundsException
     */
    public function fetchNotifications($label, $fetch = 10, $refresh = false)
    {
        // you asked for zero, so here's zero. but yes, we validated the queue name first
        if($fetch == 0) {
            return array();
        }

        $bundle = new NotificationBundle();

        $this->fetchNotificationsIntoBundle($label, $bundle, $fetch, $refresh);

        // negative value will be considered "no limit"
        if ($fetch < 0) {
            return $bundle->getAll();
        }

        return array_slice($bundle->getAll(), 0, $fetch);

    }

    /**
     * @param string             $label
     * @param NotificationBundle $bundle
     * @param bool               $refresh
     * @return void
     * @throws OutOfBoundsException
     */
    public function fetchNotificationsIntoBundle($label, NotificationBundle $bundle, $refresh = false)
    {
        $queueName = $this->findQueue($label);
        $queue = $this->queues[$queueName];

        if (empty($this->instanceCache[$queueName]) || $refresh) {
            $this->instanceCache[$queueName] = $queue->fetch();
        }

        $bundle->appendInstances($this->instanceCache[$queueName]);
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

}
