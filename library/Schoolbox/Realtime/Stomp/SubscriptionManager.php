<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Stomp;


use Psr\Log\LoggerInterface;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Pipe\SelfResponder;

class SubscriptionManager {

	/** @var PipeManager */
	protected $pipeManager;

	/** @var Client */
	protected $stompClient;

	/** @var Subscription[] */
	protected $subscriptions;

	/** @var IConfigLoader */
	protected $conf;

    /** @var LoggerInterface */
    protected $log;

	/**
	 * @param Client $stompClient
	 * @param PipeManager $pipeManager
     * @param LoggerInterface $log
	 */
	public function __construct($stompClient, $pipeManager, $log) {
		// this factory requires its own loop to work
		$this->subscriptions = array();
		$this->stompClient = $stompClient;
		$this->pipeManager = $pipeManager;
		$this->conf = ConfigMapper::getInstance();
        $this->log = $log;
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
			$this->log->info("User $userId is already subscribed");

			// notify the pipe if it was supplied
			if($responder instanceof SelfResponder) {
				$responder->notifyPipe(json_encode(array('subscribe' => true)));
			}

			return false;
		}

		try {
            // this closure is executed when a stomp client connection is established
            $this->putSubscription(new Subscription($this->stompClient, $userId, $this->pipeManager, $this->log));
            // if a responder is provided, use it as a callback for success
            if($responder instanceof SelfResponder) {
                $responder->notifyPipe(json_encode(array('subscribe' => true)));
            }

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
		return sprintf("/amq/queue/".UserQueue::QUEUE_UNREAD_MESSAGES, INSTANCE_ID, $userId);
	}

	/**
	 * @param int $userId
	 * @return bool
	 */
	public function userHasSubscription($userId) {
		return array_key_exists($this->userSubscriptionDestination($userId), $this->subscriptions);
	}

} 