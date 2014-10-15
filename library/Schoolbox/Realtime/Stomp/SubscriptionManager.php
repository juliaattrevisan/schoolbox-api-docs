<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Stomp;


use Alaress\Async\AMQP\AMQPResponseHandlerInterface;
use Psr\Log\LoggerInterface;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\QueueFactory;
use UnexpectedValueException;

class SubscriptionManager {

	/** @var AMQPResponseHandlerInterface */
	protected $handler;

	/** @var Client */
	protected $stompClient;

	/** @var Subscription[] */
	protected $subscriptions;

	/** @var IConfigLoader */
	protected $conf;

    /** @var LoggerInterface */
    protected $log;

    /** @var QueueFactory */
    protected $qf;


    /**
     * @param Client                       $stompClient
     * @param AMQPResponseHandlerInterface $handler
     * @param LoggerInterface              $log
     * @param QueueFactory                 $qf
     * @param IConfigLoader                $conf
     * @throws UnexpectedValueException
     */
    public function __construct(
        Client $stompClient,
        AMQPResponseHandlerInterface $handler,
        LoggerInterface $log,
        QueueFactory $qf,
        IConfigLoader $conf = null
    ) {
        $this->subscriptions = array();
        $this->handler       = $handler;
        $this->stompClient   = $stompClient;
        $this->conf          = $conf ? : ConfigMapper::getInstance();
        $this->log           = $log;
        $this->qf            = $qf;
    }


	/**
	 * Add a subscription for the given user.
	 *
	 * @param int $userId
	 * @return bool
	 */
	public function subscribe($userId) {
		if($this->userHasSubscription($userId)) {
			$this->log->info("User $userId is already subscribed");
			return true;
		}

		try {
            $queueName = $this->userSubscriptionDestination($userId);
			$this->putSubscription(new Subscription($userId, $queueName, $this->stompClient, $this->handler, $this->log));
			return true;
		}
		// the rabbit is probably offline
		catch(ConnectionException $e) {
			$notif = new Notif(Notif::TOPIC_NOTIFICATION);
			$notif->alert("Cannot bind to messaging system: check your STOMP bindings and that RabbitMQ is running.");
			$this->log->alert('Rabbitmq is offline or STOMP misconfigured: '. $e->getMessage(), array($e));
			return false;
		}
	}

	/**
	 * @param Subscription $subscription
	 */
	public function putSubscription($subscription) {
		$destination = $subscription->getDestination();
		$this->log->notice("Adding subscription to $destination (id:{$subscription->getId()})");
		$this->subscriptions[$destination] = $subscription;
	}


    /**
     * Attempts removal of subscriptions of the given userIds.
     * Returns an array of booleans indexed by userId, each value indicating whether or not the subscription was removed
     *
     * @param array $userIds
     * @return array
     */
    public function removeSubscriptions($userIds) {
        $result = array();
        foreach($userIds as $id) {
            $result[$id] = $this->removeSubscription($id);
        }
        return $result;
    }


    /**
     * Attempts removal of a user subscription. Returns boolean of success/failure
     *
     * @param int $userId
     * @return bool
     */
    public function removeSubscription($userId) {
        if(!$this->userHasSubscription($userId)) {
            return false;
        }

        $dest = $this->userSubscriptionDestination($userId);

        // call an explicit unsubscription to unhook from STOMP
        $this->subscriptions[$dest]->unsubscribe();

        // delete the reference, which hopefully calls the destructor
        unset($this->subscriptions[$dest]);

        return true;
    }


	/**
	 * @param int $userId
	 * @param bool $generateIfAbsent
	 * @throws SubscriptionException
	 * @return Subscription
	 */
	public function getUserSubscription($userId, $generateIfAbsent = true) {

		$dest = $this->userSubscriptionDestination($userId);

		if(!array_key_exists($dest, $this->subscriptions)) {
			if($generateIfAbsent) {
				$this->subscribe($userId);
				if(array_key_exists($dest, $this->subscriptions)) {
					return $this->subscriptions[$dest];
				}
			}
			throw new SubscriptionException("No subscription for user $userId");
		}
		return $this->subscriptions[$dest];
	}

	/**
	 * @param int $userId
	 * @return string
	 */
	protected function userSubscriptionDestination($userId) {
        // this whole "build an object just to get its name" might seem like overkill, but part of that
        // instantiation is to ensure that the AMQP queue actually *exists*, which makes it all worth it.
        return Subscription::STOMP_QUEUE_PREFIX . $this->qf->buildForUser($userId)->getName();
	}

	/**
	 * @param int $userId
	 * @return bool
	 */
	public function userHasSubscription($userId) {
		return array_key_exists($this->userSubscriptionDestination($userId), $this->subscriptions);
	}

} 