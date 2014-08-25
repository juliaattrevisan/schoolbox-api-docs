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
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\Notif\Queue\Builder\AMQPBuilder;
use Schoolbox\Notif\Queue\Builder\Plan;
use Schoolbox\Notif\Queue\UserQueue;

class BinderPlan extends Plan
{

    /**
     * Binder uses the AMQP queues
     *
     * @param AMQPInterface   $amqp
     * @param UserQueueBinder $binder
     * @param Generator       $generator
     */
    function __construct(AMQPInterface $amqp, UserQueueBinder $binder = null, Generator $generator = null)
    {
        $this->setBuilder(AMQPBuilder::STORE, new AMQPBuilder($amqp, $binder, $generator));

        $this->addIntention(AMQPBuilder::STORE, UserQueue::Q_UNREAD, "*.instant.#");
        $this->addIntention(AMQPBuilder::STORE, UserQueue::Q_TOSORT, "*.notify.#");
    }

}