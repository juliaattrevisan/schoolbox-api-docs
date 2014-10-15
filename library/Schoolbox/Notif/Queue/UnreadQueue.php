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
use Alaress\Async\Queue\AMQPQueue;
use Alaress\Async\UserQueue\UserQueue;

class UnreadQueue extends AMQPQueue {


    /**
     * @param AMQPInterface $connector
     * @param int           $userId
     */
    public function __construct(AMQPInterface $connector, $userId)
    {
        $name = $userId . '.' . UserQueue::Q_UNREAD;
        parent::__construct($connector, $name, "*.instant.user.$userId.#");
    }


}