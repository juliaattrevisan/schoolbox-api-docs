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
use Schoolbox\Notif\Queue\ToSortQueue;

class ToSortUserQueue extends UserQueue {


    /**
     * @param AMQPInterface $conn
     * @param int           $userId
     */
    public function __construct(AMQPInterface $conn, $userId)
    {
        $queues = array(
            new ToSortQueue($conn, $userId),
        );
        parent::__construct($queues);
    }


    /**
     * Drain this UserQueue
     */
    public function drain()
    {
        // noop: this UQ does not 'drain'
    }

}