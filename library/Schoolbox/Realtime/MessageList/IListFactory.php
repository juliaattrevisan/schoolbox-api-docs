<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;

/**
 * Interface IListFactory
 *
 * @package Schoolbox\Realtime\MessageList
 */
interface IListFactory
{

    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function listed($userId);


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function unread($userId);


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function digest($userId);


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function instant($userId);


    /**
     * Create a "queue" that when a notification is pushed onto it:
     * * retracts any matches for the notification from any other queues
     *   (Match = has same message string, href and params)
     *
     * @return QueueInterface
     */
    public function retract(): QueueInterface;


    /**
     * Create a "queue" that when a notification is pushed onto it:
     * * retracts any notifications like it from any other queues
     *   (Like = has same message string and href)
     *
     * @return QueueInterface
     */
    public function retractLike(): QueueInterface;


    /**
     * Returns a queue which handles message set for a given event object.
     * @param string $eventObjectId
     * @return QueueInterface
     */
    public function eventObjectList($eventObjectId): QueueInterface;
}
