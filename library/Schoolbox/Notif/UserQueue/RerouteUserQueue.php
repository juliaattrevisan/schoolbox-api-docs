<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\UserQueue;


use Alaress\Async\UserQueue\UserQueue;
use Predis\ClientInterface;
use Schoolbox\Notif\Queue\DigestQueue;
use Schoolbox\Notif\Queue\ListedQueue;

class RerouteUserQueue extends UserQueue {


    /**
     * @param ClientInterface $redis
     * @param int             $userId
     */
    public function __construct(ClientInterface $redis, $userId)
    {
        parent::__construct(array(
            new ListedQueue($redis, $userId),
            new DigestQueue($redis, $userId)
        ));
    }


    /**
     * Drain this UserQueue
     */
    public function drain()
    {
        // noop
    }

} 