<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Builder;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Queue\AMQPQueue;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\User;

class AMQPBuilder implements BuilderInterface
{
    const STORE = 'amqp';

    /** @var AMQPInterface */
    protected $conn;

    /** @var Generator */
    protected $generator;

    /** @var UserQueueBinder */
    protected $binder;


    /**
     * @param AMQPInterface   $conn
     * @param UserQueueBinder $binder
     * @param Generator       $generator
     */
    public function __construct(AMQPInterface $conn, UserQueueBinder $binder = null, Generator $generator = null)
    {
        $this->conn      = $conn;
        $this->binder    = $binder ? : new UserQueueBinder();
        $this->generator = $generator ? : new Generator();
    }


    /**
     * @param string $label
     * @param int    $id
     * @return string
     */
    public function createQueueName($label, $id)
    {
        return INSTANCE_ID . ".u.$id.$label";
    }


    /**
     * @param Plan $plan
     * @param User $user
     * @return AMQPQueue[]
     */
    public function generateQueues(Plan $plan, User $user)
    {
        if (!$plan->containsIntention(self::STORE)) {
            return array();
        }

        $userBindings = $this->generator->generateBindingsForUser($user);
        $userExchange = $this->conn->getSecondaryExchange($user->getId());

        $this->conn->declareExchange($userExchange);

        $queues = array();

        foreach ($plan->getIntention(self::STORE) as $label => $routingKey) {
            $queues[] = new AMQPQueue($this->conn, $this->createQueueName($label, $user->getId()), $routingKey);
        }

        $this->binder->setBindings($userBindings)
                     ->bindToAMQP($this->conn, $userExchange, $queues);

        return $queues;
    }


    /**
     * @param Plan $plan
     * @param int  $userId
     * @return array
     */
    public function generateQueueNames(Plan $plan, $userId)
    {
        if (!$plan->containsIntention(self::STORE)) {
            return array();
        }

        $queues = array();

        foreach (array_keys($plan->getIntention(self::STORE)) as $label) {
            $queues[] = $this->createQueueName($label, $userId);
        }

        return $queues;
    }

}