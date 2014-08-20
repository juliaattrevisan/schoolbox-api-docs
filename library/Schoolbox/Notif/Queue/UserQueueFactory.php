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
use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use RuntimeException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\Message\External\TargetedConverter;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
use Schoolbox\Resource\Category;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Handles Dependency Injection for the UserQueue class
 *
 * Class UserQueueFactory
 * @package Schoolbox\Notif\Queue
 */
class UserQueueFactory {

	/** @var AMQPInterface */
	protected $mqConnector;

    /** @var GeneratorInterface */
    protected $generator;

	/** @var ControlInterface */
	protected $bunnyController;

	/** @var UserMapper */
	protected $userMapper;

	/** @var array */
	protected $existingBindings = null;


	/**
	 * @param AMQPInterface      $mqConnector
     * @param GeneratorInterface $generator
	 */
	public function __construct($mqConnector = null, $generator = null) {
		$this->setMessageQueueConnector($mqConnector ?: MQConnector::getInstance());
        $this->setGenerator($generator ?: new Generator());
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
	 * @param UserMapper $userMapper
	 * @return self
	 */
	public function setUserMapper($userMapper) {
		$this->userMapper = $userMapper;
		return $this;
	}



	/**
	 * @return \Schoolbox\UserMapper
	 */
	public function getUserMapper() {
		if(!$this->userMapper) {
			$this->setUserMapper(UserMapper::getInstance());
		}
		return $this->userMapper;
	}



	/**
	 * Creates bindings for a User's queue, but doesn't actually attempt message retrieval.
	 * @param User|int $user
	 * @param bool $simulate
	 * @return UserQueue
	 */
	public function bindUserQueue($user, $simulate = false) {
		$user = $user instanceof User ? $user : $this->getUserMapper()->findById($user);
        $bindings = $this->getGenerator()->generateBindingsForUser($user);
		return new UserQueue($bindings, $this->mqConnector, $simulate);
	}


	/**
	 * Build the queue for the given User, including binding generation
	 *
	 * @param int $userId
	 * @param int $limit
	 * @param bool $bind
	 * @return UserQueue
	 */
	public function buildUserQueue($userId, $limit = 10, $bind = true) {
		if($bind) {
			$q = $this->bindUserQueue($userId);
		}
		else {
			$q = new UserQueue(array('user' => array(
				'id' => $userId
			)), $this->mqConnector);
		}
		$q->getNotifications($limit);
		return $q;
	}


	/**
	 * Destroy all routing bindings for the given user.
	 * Returns the number of bindings deleted, or -1 on error.
	 *
	 * @param int $userId
	 * @param bool $simulate
	 * @return int
	 */
	public function destroyUserBindings($userId, $simulate = false) {

		if(!$this->existingBindings) {
			$this->cacheExistingBindings();
		}

		if(!isset($this->existingBindings[$userId])) {
			return 0;
		}

		$count = 0;

		if(!$simulate) {
			$userExchange = $this->mqConnector->getSecondaryExchange($userId);

			foreach($this->existingBindings[$userId] as $routingKey) {
				$this->mqConnector->unbindExchange($userExchange->getName(), $routingKey);
				++$count;
			}
		}

		return $count;
	}


	/**
	 * Retrieve all existing bindings from RabbitMQ and cache into an array, indexed by username
	 *
	 * @return int
	 */
	protected function cacheExistingBindings() {
		$this->existingBindings = array();

		try {
			$bindings = $this->getRabbitController()->listBindings(array('destination_name', 'routing_key'));
		}
		catch(RuntimeException $e) {
			return -1;
		}

		if(!count($bindings)) {
			return 0;
		}

		$count = 0;
		$prefix = $this->mqConnector->getSecondaryExchange('')->getName();

		foreach($bindings as $binding) {
			// only parse line if this is a user exchange
			if(substr($binding['destination_name'], 0, strlen($prefix)) != $prefix) {
				continue;
			}

			$username = substr($binding['destination_name'], strlen($prefix));

			$this->existingBindings[$username][] = $binding['routing_key'];

			++$count;
		}

		return $count;
	}


	/**
	 * Returns whether or not a given message is sitting in the given user's listed or digest queues.
	 * Use this method shrewdly - its expense is proportional to the length of the given user's queues.
	 *
	 * @param int $userId
	 * @param Instance $instance
	 * @throws InvalidArgumentException
	 * @return bool
	 */
	public function userHasSeen($userId, $instance) {
		$result = false;

		$qs = array(
			UserQueue::getUserListedQueueName($userId),
			UserQueue::getUserDigestQueueName($userId),
		);

		foreach($qs as $q) {
			$nack = array();
			try {
				while($m = $this->mqConnector->readFromQueue($q)) {
					// store list of retrieved messages to NACK later
					$nack[] = $m;
					$unjson = json_decode($m->body, true);

					try {
						$compare = Instance::jsonUnserialize($unjson['message']);
					}
					catch(InvalidArgumentException $e) {
						// TODO once TargetedMessage fully deprecated this catch can be removed
						$conv = new TargetedConverter();
						$compare = $conv->convert($unjson['message']);
					}

					if($instance->getBaseHash() == $compare->getBaseHash()) {
						if($instance->getParamHash() == $compare->getParamHash()) {
							$result = true;
							break;
						}
					}
				}
				// push any messages we retrieved back onto the queue
				$this->mqConnector->rejectMessages($nack);
			}
			catch(InvalidArgumentException $e) {
				// regardless of anything else, you MUST reject the messages you retrieved
				$this->mqConnector->rejectMessages($nack);
				throw $e;
			}

			// if we returned true, don't bother continuing
			if($result) {
				break;
			}
		}

		return $result;
	}

}
