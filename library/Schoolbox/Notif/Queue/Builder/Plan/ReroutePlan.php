<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Builder\Plan;


use Predis\ClientInterface;
use Schoolbox\Notif\Queue\Builder\Plan;
use Schoolbox\Notif\Queue\Builder\RedisBuilder;
use Schoolbox\Notif\Queue\UserQueue;

class ReroutePlan extends Plan {

    /**
     * Reroute plan uses the Redis queues
     *
     * @param ClientInterface $redis
     */
    function __construct(ClientInterface $redis)
    {
        $this->setBuilder(RedisBuilder::STORE, new RedisBuilder($redis));
        $this->addIntention(RedisBuilder::STORE, UserQueue::Q_DIGEST);
        $this->addIntention(RedisBuilder::STORE, UserQueue::Q_LISTED);
    }

} 