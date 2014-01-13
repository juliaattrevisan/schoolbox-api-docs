<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;


use React\Stomp\Client;
use React\Stomp\Factory;
use Schoolbox\Realtime\Stomp\Subscription;
use Schoolbox\Realtime\Stomp\SubscriptionException;
use Schoolbox\User;

class SubscriptionManager {

	/** @var PipeManager */
	protected $pipeManager;

	/** @var Factory */
	protected $stompFactory;

	/** @var Subscription[] */
	protected $subscriptions;

	public function __construct($stompFactory, $pipeManager) {
		$this->stompFactory = $stompFactory;
		$this->subscriptions = array();
		$this->pipeManager = $pipeManager;
	}

	/**
	 * Add a subscription for the given user. If a pipe is provided, it will be notified upon success.
	 *
	 * @param User $user
	 * @param Pipe $pipe
	 * @return bool
	 */
	public function subscribe($user, $pipe = null) {
		if($this->userHasSubscription($user)) {
			$this->pipeManager->v("User {$user->getUsername()} is already subscribed");

			// notify the pipe if it was supplied
			if($pipe instanceof Pipe) {
				$this->getPipeManager()->sendToPipe($pipe, json_encode(array('subscribe' => true)));
			}

			return false;
		}

		$this->pipeManager->v('SUBSCRIBE', '+');

		$client = $this->stompFactory->createClient(array(
			'host'      => '127.0.0.1',
			'port'      => 61613,
			'vhost'     => '/',
			'login'     => 'guest',
			'passcode'  => 'guest',
		));

		$self = $this;
		$destination = $this->userSubscriptionDestination($user);

		$client->connect()->then(function($client) use ($destination, $self, $user, $pipe) {
			/** @var Client $client */
			// this closure is executed when a stomp client connection is established
			$self->putSubscription($destination, new Subscription($destination, $client, $user, $self->getPipeManager()));

			// if a pipe is provided, use it as a callback for success
			if($pipe instanceof Pipe) {
				$self->getPipeManager()->sendToPipe($pipe, json_encode(array('subscribe' => true)));
			}
		});

		return true;
	}

	/**
	 * @param string $destination
	 * @param Subscription $subscription
	 */
	public function putSubscription($destination, $subscription) {
		$this->pipeManager->v("Adding subscription to $destination (id:{$subscription->getId()})");
		$this->subscriptions[$destination] = $subscription;
	}

	/**
	 * @param User $user
	 * @param bool $generateIfAbsent
	 * @throws Stomp\SubscriptionException
	 * @return Subscription
	 */
	public function getUserSubscription($user, $generateIfAbsent = true) {

		$dest = $this->userSubscriptionDestination($user);

		if(!array_key_exists($dest, $this->subscriptions)) {
			if($generateIfAbsent) {
				$this->subscribe($user);
				if(array_key_exists($dest, $this->subscriptions)) {
					return $this->subscriptions[$dest];
				}
			}
			throw new SubscriptionException("No subscription for {$user->getUsername()}");
		}
		return $this->subscriptions[$dest];
	}

	/**
	 * @param User $user
	 * @return string
	 */
	protected function userSubscriptionDestination($user) {
		$username = $user->getUsername();
		return "/amq/queue/u.$username.unread";
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function userHasSubscription($user) {
		return array_key_exists($this->userSubscriptionDestination($user), $this->subscriptions);
	}


	/**
	 * @return PipeManager
	 */
	public function getPipeManager() {
		return $this->pipeManager;
	}
} 