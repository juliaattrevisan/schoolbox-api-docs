<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue\Bindings;


use Alaress\Async\AMQP\AMQPExchange;
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\AMQP\Bunny\ControlInterface;
use Alaress\Async\AMQP\Bunny\BunnyControl as Rabbit;
use Alaress\Async\Queue\AMQPQueue;
use OutOfBoundsException;
use RuntimeException;
use Schoolbox\Notif\AbstractQueueFactory;
use Schoolbox\User;

class UserQueueBinder
{

    /** @var int */
    protected $userId = 0;

    /** @var array */
    protected $bindings = array();

    /** @var array */
    protected $existingBindings = null;

    /** @var AMQPInterface */
    protected $amqp;

    /** @var ControlInterface */
    protected $rabbitCtrl;

    /** @var AbstractQueueFactory */
    protected $queueFactory;

    /** @var Generator */
    protected $generator;


    /**
     * @param AMQPInterface        $amqp
     * @param ControlInterface     $rabbitCtrl
     * @param AbstractQueueFactory $qFactory
     * @param Generator            $gen
     */
    public function __construct(AMQPInterface $amqp, ControlInterface $rabbitCtrl, AbstractQueueFactory $qFactory, Generator $gen)
    {
        $this->amqp         = $amqp;
        $this->rabbitCtrl   = $rabbitCtrl;
        $this->queueFactory = $qFactory;
        $this->generator    = $gen;
    }


    /**
     * @param User $user
     * @return self
     */
    public function setUser(User $user)
    {
        $this->setBindings($this->generator->generateBindingsForUser($user));
        return $this;
    }


    /**
     * @param array $bindings
     * @return self
     */
    protected function setBindings(array $bindings)
    {
        $this->bindings = array();

        $this->userId = $bindings['user']['id'];

        unset($bindings['user']);

        // the user's personal routing keys
        $this->bindings = array(
            "*.notify.user.$this->userId.#",
            "*.digest.user.$this->userId.#",
            "*.instant.user.$this->userId.#"
        );

        // all remaining bindings specified
        foreach ($bindings as $type => $binds) {
            foreach ($binds as $id) {
                $this->bindings[] = "*.notify.$type.$id";
            }
        }

        return $this;
    }


    /**
     * @return array
     */
    public function getBindings()
    {
        $this->validate();
        return $this->bindings;
    }


    /**
     * Binds to AMQP. Returns the number of bindings created.
     *
     * @param AMQPExchange  $exchange
     * @param AMQPQueue[]   $queues
     * @throws OutOfBoundsException
     * @return int
     */
    public function bindToAMQP(AMQPExchange $exchange, array $queues)
    {
        $this->validate();

        $exchangeName = $exchange->getName();

        // exchange things
        $this->amqp->declareExchange($exchange);

        foreach ($this->bindings as $key) {
            $this->amqp->bindExchange($exchangeName, $key);
        }

        // queue things
        foreach ($queues as $q) {
            $queueName = $q->getName();
            $routeKey  = $q->getRoutingKey();
            $this->amqp->declareQueue($queueName);
            $this->amqp->bindQueue($queueName, $routeKey, $exchangeName);
        }

        return count($this->bindings);
    }


    /**
     * Destroy all routing bindings for the given user.
     * Returns the number of bindings deleted, or 0 on error.
     *
     * @return int
     */
    public function destroyUserBindings()
    {
        $this->validate();

        if (!$this->existingBindings) {
            $this->cacheExistingBindings();
        }

        if (!isset($this->existingBindings[$this->userId])) {
            return 0;
        }

        $count = 0;

        $userExchange = $this->amqp->getSecondaryExchange($this->userId);

        foreach ($this->existingBindings[$this->userId] as $routingKey) {
            $this->amqp->unbindExchange($userExchange->getName(), $routingKey);
            ++$count;
        }


        return $count;
    }


    /**
     * Retrieve all existing bindings from RabbitMQ and cache into an array, indexed by username
     *
     * @return int
     */
    protected function cacheExistingBindings()
    {
        $this->existingBindings = array();

        try {
            $bindings = $this->rabbitCtrl->listBindings(array(Rabbit::BINDING_DEST, Rabbit::BINDING_KEY));
        } catch (RuntimeException $e) {
            return -1;
        }

        if (!count($bindings)) {
            return 0;
        }

        $count  = 0;
        $prefix = $this->amqp->getSecondaryExchange('')->getName();

        foreach ($bindings as $binding) {
            // only parse line if this is a user exchange
            if (substr($binding[Rabbit::BINDING_DEST], 0, strlen($prefix)) != $prefix) {
                continue;
            }

            $userId = substr($binding[Rabbit::BINDING_DEST], strlen($prefix));

            $this->existingBindings[$userId][] = $binding[Rabbit::BINDING_KEY];

            ++$count;
        }

        return $count;
    }


    /**
     * Drop the cache of existing bindings
     */
    public function dropCache()
    {
        $this->existingBindings = null;
    }


    /**
     * @param array $kinds
     * @return array
     */
    public function performBind($kinds)
    {
        $this->validate();

        $queues = array();

        foreach ($kinds as $k) {
            $queues[] = $this->queueFactory->setKind($k)->buildForUser($this->userId);
        }

        // remove the current user's bindings
        $removed = $this->destroyUserBindings();

        // AMQP-specific things we need
        $exchange = $this->amqp->getSecondaryExchange($this->userId);


        // generate and attach the bindings we require
        $added = $this->bindToAMQP($exchange, $queues);

        return array($removed, $added);
    }


    /**
     * Verify that a valid user has been imported
     *
     * @throws OutOfBoundsException
     */
    protected function validate()
    {
        if (empty($this->bindings) || !$this->userId) {
            throw new OutOfBoundsException("A user with bindings has not been specified");
        }
    }


    /**
     * @param array $currentUserIds - any user NOT in this list will have their bindings destroyed
     */
    public function destroyStaleBindings(array $currentUserIds)
    {
        $this->cacheExistingBindings();

        $queueCache = array();

        // collect a cache of current AMQP queues
        foreach ($this->rabbitCtrl->listQueues(array('name')) as $queue) {
            if (preg_match('`\\.u\\.(\\d+)\\.`', $queue['name'], $matches)) {
                $queueCache[$matches[1]][] = $queue['name'];
            }
        }

        // remove current users from the list
        foreach ($currentUserIds as $currentUserId) {
            unset($this->existingBindings[$currentUserId]);
        }

        foreach (array_keys($this->existingBindings) as $expiredUserId) {
            // cheat our way around self::validate()
            $this->userId = $expiredUserId;
            $this->bindings = array('fake');

            // remove this user's bindings
            $this->destroyUserBindings();

            // aaaaaand delete their queues for good measure
            if (isset($queueCache[$expiredUserId])) {
                foreach ($queueCache[$expiredUserId] as $queue) {
                    $this->amqp->deleteQueue($queue);
                }
            }

        }
    }

}
