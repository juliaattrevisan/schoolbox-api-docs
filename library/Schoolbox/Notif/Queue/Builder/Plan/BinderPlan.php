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
use Schoolbox\Notif\Queue\Builder\AMQPBuilder;
use Schoolbox\Notif\Queue\Builder\Plan;
use Schoolbox\Notif\Queue\UserQueue;

class BinderPlan extends Plan
{

    /**
     * Binder uses the AMQP queues
     *
     * @param AMQPInterface $amqp
     */
    function __construct(AMQPInterface $amqp)
    {
        $this->setBuilder(AMQPBuilder::STORE, new AMQPBuilder($amqp));

        $this->addIntention(AMQPBuilder::STORE, UserQueue::Q_UNREAD, "*.instant.#");
        $this->addIntention(AMQPBuilder::STORE, UserQueue::Q_TOSORT, "*.notify.#");
    }

}