<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Builder\Plan;


use Alaress\Async\AMQP\AMQPInterface;
use Predis\ClientInterface;
use Schoolbox\Notif\Queue\Builder\AMQPBuilder;
use Schoolbox\Notif\Queue\Builder\Plan;
use Schoolbox\Notif\Queue\Builder\RedisBuilder;
use Schoolbox\Notif\Queue\UserQueue;

class FullPlan extends Plan {

    /**
     * You should try to not require this class.
     * Only link up to the queues you need in any specific instance
     *
     * @param AMQPInterface   $amqp
     * @param ClientInterface $redis
     */
    function __construct(AMQPInterface $amqp, ClientInterface $redis)
    {
        $this->setBuilder(AMQPBuilder::STORE, new AMQPBuilder($amqp));
        $this->setBuilder(RedisBuilder::STORE, new RedisBuilder($redis));

        $this->addIntention(AMQPBuilder::STORE, UserQueue::Q_UNREAD, "*.instant.#");
        $this->addIntention(AMQPBuilder::STORE, UserQueue::Q_TOSORT, "*.notify.#");
        $this->addIntention(RedisBuilder::STORE, UserQueue::Q_LISTED);
        $this->addIntention(RedisBuilder::STORE, UserQueue::Q_DIGEST);
    }

} 