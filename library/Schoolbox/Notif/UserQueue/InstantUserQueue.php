<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\UserQueue;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\UserQueue\UserQueue;
use Predis\ClientInterface;
use Schoolbox\Notif\Queue\ListedQueue;
use Schoolbox\Notif\Queue\UnreadQueue;

class InstantUserQueue extends UserQueue {

    /** @var string */
    protected $dname;


    /**
     * @param ClientInterface $redis
     * @param AMQPInterface   $conn
     * @param int             $userId
     */
    public function __construct(ClientInterface $redis, AMQPInterface $conn, $userId)
    {
        $unread = new UnreadQueue($conn, $userId);
        $listed = new ListedQueue($redis, $userId);

        $this->dname = $unread->getName();

        parent::__construct(array($listed, $unread));
    }


    /**
     * Drain this UserQueue.
     *
     * In this context, this is semantically equivalent to "mark all as read", since it won't drain the listed queue.
     * Also, note that it will have no effect if the socket daemon has acquired a lock on the unread messages.
     */
    public function drain()
    {
        $this->queues[$this->dname]->drain();
    }

}
