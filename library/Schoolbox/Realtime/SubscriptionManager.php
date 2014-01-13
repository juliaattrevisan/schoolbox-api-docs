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

	/** @var Verboser */
	public $verboser;

	/** @var Factory */
	protected $stompFactory;

	/** @var Subscription[] */
	protected $subscriptions;

	public function __construct($stompFactory, $verboser) {
		$this->stompFactory = $stompFactory;
		$this->subscriptions = array();
		$this->verboser = $verboser;
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function subscribe($user) {

		if($this->userHasSubscription($user)) {
			$this->verboser->v("User {$user->getUsername()} is already subscribed");
			return false;
		}

		$this->verboser->v('SUBSCRIBE', '+');

		$client = $this->stompFactory->createClient(array(
			'host'      => '127.0.0.1',
			'port'      => 61613,
			'vhost'     => '/',
			'login'     => 'guest',
			'passcode'  => 'guest',
		));

		$self = $this;
		$destination = $this->userSubscriptionDestination($user);

		$client->connect()->then(function($client) use ($destination, $self) {
			/** @var Client $client */
			// this closure is executed when a stomp client connection is established
			$self->putSubscription($destination, new Subscription($destination, $client, $self->verboser));
		});

		return true;
	}

	/**
	 * @param string $destination
	 * @param Subscription $subscription
	 */
	public function putSubscription($destination, $subscription) {
		$this->verboser->v("Adding subscription to $destination (id:{$subscription->getId()})");
		$this->subscriptions[$destination] = $subscription;
	}

	/**
	 * @param User $user
	 * @param $generateIfAbsent
	 * @throws Stomp\SubscriptionException
	 * @return Subscription
	 */
	public function getUserSubscription($user, $generateIfAbsent) {

		$dest = $this->userSubscriptionDestination($user);

		if(!array_key_exists($dest, $this->subscriptions)) {
			if($generateIfAbsent) {
				$this->subscribe($user);
			}
			else {
				throw new SubscriptionException("No subscription for {$user->getUsername()}");
			}
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

} 