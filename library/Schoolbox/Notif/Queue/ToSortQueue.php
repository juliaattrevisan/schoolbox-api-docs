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

/**
 * Class ToSortQueue
 *
 * @deprecated
 * @package Schoolbox\Notif\Queue
 */
class ToSortQueue extends AMQPQueue {


    /**
     * @deprecated
     * @param AMQPInterface $connector
     * @param int          $userId
     */
    public function __construct(AMQPInterface $connector, $userId)
    {
        $name = $userId . '.' . UserQueue::Q_TOSORT;
        parent::__construct($connector, $name, '*.notify.#');
    }

} 