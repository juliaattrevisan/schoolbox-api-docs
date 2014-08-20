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
use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\AMQP\Bunny\ControlInterface;
use Alaress\Async\Queue\AMQPQueue;
//use Alaress\Async\Queue\RedisQueue;
use Alaress\Redis;
use Predis\Client;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Notif\MQ\Test\MockAMQPConnector;
use Schoolbox\Notif\MQ\Test\MockBunnyControl;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\User;

/**
 * Handles Dependency Injection for the UserQueue class
 *
 * Class UserQueueFactory
 * @package Schoolbox\Notif\Queue
 */
class UserQueueFactory {

	/** @var AMQPInterface */
	protected $mqConnector;

    /** @var Client */
    protected $redisClient;

    /** @var GeneratorInterface */
    protected $generator;

    /** @var UserQueueBinder */
    protected $binder;

	/** @var ControlInterface */
	protected $bunnyController;

	/** @var array */
	protected $existingBindings = null;


    /**
     * @param AMQPInterface      $mqConnector
     * @param Client             $redisClient
     * @param GeneratorInterface $generator
     * @param UserQueueBinder    $binder
     */
    public function __construct($mqConnector = null, $redisClient = null, $generator = null, $binder = null)
    {
        if (!$redisClient instanceof Client) {
            $r = new Redis();
            $redisClient = $r->getClientInstance();
        }

        $this->setMessageQueueConnector($mqConnector ? : MQConnector::getInstance())
             ->setRedisClient($redisClient)
             ->setGenerator($generator ? : new Generator())
             ->setBinder($binder ? : new UserQueueBinder());
    }


    /**
     * @param GeneratorInterface $generator
     * @return self
     */
    public function setGenerator($generator)
    {
        $this->generator = $generator;
        return $this;
    }


    /**
     * @return GeneratorInterface
     */
    public function getGenerator()
    {
        return $this->generator;
    }


    /**
     * @param UserQueueBinder $binder
     * @return self
     */
    public function setBinder($binder)
    {
        $this->binder = $binder;
        return $this;
    }


    /**
     * @return UserQueueBinder
     */
    public function getBinder()
    {
        return $this->binder;
    }


    /**
     * @param Client $redisClient
     * @return self
     */
    public function setRedisClient($redisClient)
    {
        $this->redisClient = $redisClient;
        return $this;
    }


    /**
     * @return Client
     */
    public function getRedisClient()
    {
        return $this->redisClient;
    }


	/**
	 * @param AMQPInterface $mqConnector
	 * @return self
	 */
	public function setMessageQueueConnector($mqConnector) {
		$this->mqConnector = $mqConnector;
		return $this;
	}


	/**
	 * @return AMQPInterface
	 */
	public function getMessageQueueConnector() {
		return $this->mqConnector;
	}


	/**
	 * @param ControlInterface $controller
	 * @return self
	 */
	public function setRabbitController($controller) {
		$this->bunnyController = $controller;
		return $this;
	}


	/**
	 * @return ControlInterface
	 */
	public function getRabbitController() {
		if(!$this->bunnyController) {
			$this->setRabbitController(new BunnyControl());
		}
		return $this->bunnyController;
	}


    /**
     * Creates bindings for a User's queue, but doesn't actually attempt message retrieval.
     *
     * @param User $user - do yourself a favour and make sure this is fully hydrated, otherwise your bindings will be whack
     * @return UserQueue
     */
    protected function bindUserQueue($user)
    {
        $mqConn   = $this->getMessageQueueConnector();
//        $redis    = $this->getRedisClient();
        $userId   = $user->getId();
        $bindings = $this->getGenerator()->generateBindingsForUser($user);
        $exchange = $mqConn->getSecondaryExchange($userId);

        $mqConn->declareExchange($exchange);

        $queues = array(
            new AMQPQueue($mqConn, UserQueue::getUserUnreadQueueName($userId), "*.instant.user.$userId.#"),
            new AMQPQueue($mqConn, UserQueue::getUserToSortQueueName($userId), '*.notify.#'),
//            new RedisQueue($redis, UserQueue::getUserListedQueueName($userId)),
//            new RedisQueue($redis, UserQueue::getUserDigestQueueName($userId))
        );

        $this->getBinder()
             ->setBindings($bindings)
//             ->prepareRedis($redis, $queues)
             ->prepareAMQP($mqConn, $exchange, $queues);

        return new UserQueue($queues);
}


    /**
     * Build the queue for the given User, including binding generation
     *
     * @param User $user
     * @param int  $limit
     * @return UserQueue
     */
    public function buildUserQueue($user, $limit = 10)
    {
        $q = UserQueue::getUserListedQueueName($user->getId());
        return $this->bindUserQueue($user)
                    ->fetchNotifications($q, $limit, true);
    }


    /**
     * Destroy all routing bindings for the given user.
     * Returns the number of bindings deleted, or -1 on error.
     *
     * @param int $userId
     * @param bool $simulate
     * @return int
     */
    public function destroyUserBindings($userId, $simulate = false)
    {
        // this is now a (semi-intelligent) passthrough
        $conn = $simulate ? new MockAMQPConnector() : $this->getMessageQueueConnector();
        $ctrl = $simulate ? new MockBunnyControl() : $this->getRabbitController();

        return $this->getBinder()->destroyUserBindings($conn, $ctrl, $userId);
    }

}
