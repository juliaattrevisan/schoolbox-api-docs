<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;

/**
 * Interface for factory for building message queues.
 */
interface IListFactory
{
    /**
     * Build the queue for instant messages, whether read or unread.
     *
     * @param int    $userId
     * @return QueueInterface
     */
    public function listed($userId);


    /**
     * Build the queue for only the instant messages which are unread.
     *
     * When a message is marked as read, it's removed from this queue.
     *
     * @param int $userId
     * @return QueueInterface
     */
    public function unread($userId);


    /**
     * Build the queue for messages which will be sent via the digest.
     *
     * The digest is an email which is an accumulation of all messages sent to
     * a user since the last digest was sent.
     *
     * @param int $userId
     * @return QueueInterface
     */
    public function digest($userId);


    /**
     * Build the queue for instant messages, delivered in-browser.
     *
     * This generally is a composite of the listed and unread queues.
     *
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
     *
     * @param string $eventObjectId
     * @return QueueInterface
     */
    public function eventObjectList($eventObjectId): QueueInterface;
}
