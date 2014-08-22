<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Queue;

use Alaress\Async\AMQP\Bunny\BunnyControl;
use Alaress\Async\AMQP\Bunny\ControlInterface;
use Schoolbox\Notif\Queue\Bindings\UserQueueBinder;
use Schoolbox\Notif\Queue\Builder\Plan;
use Schoolbox\User;

/**
 * Handles Dependency Injection for the UserQueue class
 *
 * Class UserQueueFactory
 * @package Schoolbox\Notif\Queue
 */
class UserQueueFactory {

    /** @var Plan */
    protected $plan;

    /** @var UserQueueBinder */
    protected $binder;

	/** @var ControlInterface */
	protected $bunnyController;

	/** @var array */
	protected $existingBindings = null;


    /**
     * @param Plan             $plan
     * @param UserQueueBinder  $binder
     * @param ControlInterface $rabbit
     */
    public function __construct(Plan $plan, UserQueueBinder $binder = null, ControlInterface $rabbit = null)
    {
        $this->setPlan($plan)
             ->setBinder($binder)
             ->setRabbitController($rabbit);
    }


    /**
     * @param Plan $plan
     * @return self
     */
    public function setPlan(Plan $plan)
    {
        $this->plan = $plan;
        return $this;
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
        if (!$this->binder) {
            $this->binder = new UserQueueBinder();
        }
        return $this->binder;
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
    protected function prepareUserQueue(User $user)
    {
        return new UserQueue($user->getId(), $this->plan->generateQueues($user));
    }


    /**
     * @param int $userId
     * @return array
     */
    public function generateUserQueueNames($userId)
    {
        return $this->plan->generateQueueNames($userId);
    }


    /**
     * Build the queue for the given User, including binding generation
     *
     * @param User $user
     * @param int  $limit
     * @return UserQueue
     */
    public function buildUserQueue(User $user, $limit = 10)
    {
        $uq = $this->prepareUserQueue($user);
        if ($limit > 0) {
            $uq->fetchNotifications($uq->findQueue(UserQueue::Q_LISTED), $limit, true);
        }
        return $uq;
    }

}
