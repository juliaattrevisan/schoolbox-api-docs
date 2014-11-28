<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\UserQueue;

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
abstract class UserQueue {

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
     * @param QueueInterface[] $userQueues
     */
    public function __construct(array $userQueues)
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
     * Drain this UserQueue
     */
    public abstract function drain();


    /**
     * @param int    $fetch
     * @param bool   $refresh
     * @return Notification[]
     * @throws OutOfBoundsException
     */
    public function fetchNotifications($fetch = 10, $refresh = false)
    {
        // you asked for zero, so here's zero
        if($fetch == 0) {
            return array();
        }

        $bundle = new NotificationBundle();

        $this->fetchNotificationsIntoBundle($bundle, $refresh);

        // negative value will be considered "no limit"
        if ($fetch < 0) {
            return $bundle->getNotifications();
        }

        return $bundle->getNotifications($fetch);
    }


    /**
     * @param NotificationBundle $bundle
     * @param bool               $refresh
     * @return void
     * @throws OutOfBoundsException
     */
    public function fetchNotificationsIntoBundle(NotificationBundle $bundle, $refresh = false)
    {
        foreach ($this->queues as $q) {
            $queueName = $q->getName();

            if (empty($this->instanceCache[$queueName]) || $refresh) {
                $this->instanceCache[$queueName] = $q->fetch();
            }

            $bundle->appendInstances($this->instanceCache[$queueName]);
        }
    }


    /**
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance) {
        foreach ($this->queues as $q) {
            if ($q->contains($instance)) {
                return true;
            }
        }
        return false;
    }

}
