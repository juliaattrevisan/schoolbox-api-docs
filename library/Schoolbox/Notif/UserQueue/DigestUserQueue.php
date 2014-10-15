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

class DigestUserQueue extends UserQueue {

    /**
     * @param ClientInterface $redis
     * @param int             $userId
     */
    public function __construct(ClientInterface $redis, $userId)
    {
        $queues = array(
            new DigestQueue($redis, $userId),
        );
        parent::__construct($queues);
    }


    /**
     * Drain this UserQueue
     */
    public function drain()
    {
        // there *is* only one, but iterate for simplicity
        foreach ($this->queues as $q) {
            $q->drain();
        }
    }

}
