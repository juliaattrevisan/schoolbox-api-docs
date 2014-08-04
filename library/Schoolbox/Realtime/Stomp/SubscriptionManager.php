<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Stomp;


use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Factory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Pipe\SelfResponder;

class SubscriptionManager {

	/** @var PipeManager */
	protected $pipeManager;

	/** @var Factory */
	protected $stompFactory;

	/** @var Subscription[] */
	protected $subscriptions;

	/** @var IConfigLoader */
	protected $conf;

	/**
	 * @param Factory $stompFactory
	 * @param PipeManager $pipeManager
	 */
	public function __construct($stompFactory, $pipeManager) {
		// this factory requires its own loop to work
		$this->subscriptions = array();
		$this->stompFactory = $stompFactory;
		$this->pipeManager = $pipeManager;
		$this->conf = ConfigMapper::getInstance();
	}

	/**
	 * Add a subscription for the given user. If a pipe is provided, it will be notified upon success.
	 *
	 * @param int $userId
	 * @param SelfResponder $responder
	 * @return bool
	 */
	public function subscribe($userId, $responder = null) {
		if($this->userHasSubscription($userId)) {
			$this->pipeManager->v("User $userId is already subscribed");

			// notify the pipe if it was supplied
			if($responder instanceof SelfResponder) {
				$responder->notifyPipe(json_encode(array('subscribe' => true)));
			}

			return false;
		}

		$this->pipeManager->v('SUBSCRIBE', '+');

		try {
			$client = $this->stompFactory->createClient(array(
				'host'      => $this->conf->get('amqp_host'),
				'port'      => $this->conf->get('amqp_stomp_port'),
				'vhost'     => '/',
				'login'     => $this->conf->get('amqp_user'),
				'passcode'  => $this->conf->get('amqp_pass'),
			));

			$self = $this;

			$client->connect()->then(function($client) use ($self, $userId, $responder) {
				/** @var Client $client */
				// this closure is executed when a stomp client connection is established
				$self->putSubscription(new Subscription($client, $userId, $self->getPipeManager()));
				// if a responder is provided, use it as a callback for success
				if($responder instanceof SelfResponder) {
					$responder->notifyPipe(json_encode(array('subscribe' => true)));
				}
			});

			return true;
		}
		// the rabbit is probably offline
		catch(ConnectionException $e) {
			$notif = new Notif(Notif::TOPIC_NOTIFICATION);
			$notif->alert("Cannot bind to messaging system: check your STOMP bindings and that RabbitMQ is running.");
			$this->pipeManager->v('Rabbitmq is offline or STOMP misconfigured: '. $e->getMessage(), '!');
			return false;
		}
	}

	/**
	 * @param Subscription $subscription
	 */
	public function putSubscription($subscription) {
		$destination = $subscription->getDestination();
		$this->pipeManager->v("Adding subscription to $destination (id:{$subscription->getId()})");
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
		return sprintf("/amq/queue/".UserQueue::QUEUE_UNREAD_MESSAGES, INSTANCE_ID, $userId);
	}

	/**
	 * @param int $userId
	 * @return bool
	 */
	public function userHasSubscription($userId) {
		return array_key_exists($this->userSubscriptionDestination($userId), $this->subscriptions);
	}


	/**
	 * @return PipeManager
	 */
	public function getPipeManager() {
		return $this->pipeManager;
	}
} 