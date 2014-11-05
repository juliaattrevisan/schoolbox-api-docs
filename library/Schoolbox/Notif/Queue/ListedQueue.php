<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;


use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\UserQueue\UserQueue;
use Predis\ClientInterface;

class ListedQueue extends RedisQueue {

    /**
     * @param ClientInterface $redis
     * @param int             $userId
     * @param int             $ttl
     */
    public function __construct(ClientInterface $redis, $userId, $ttl = self::TTL)
    {
        $name = $userId . ':' . UserQueue::Q_LISTED;
        parent::__construct($redis, $name, $ttl);
    }

}
