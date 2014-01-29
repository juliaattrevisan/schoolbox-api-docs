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
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Pipe\Pipe;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Pipe\Responder;
use Schoolbox\User;

class SubscriptionManager {

	/** @var PipeManager */
	protected $pipeManager;

	/** @var Factory */
	protected $stompFactory;

	/** @var Subscription[] */
	protected $subscriptions;

	public function __construct($stompFactory, $pipeManager) {
		// this factory requires its own loop to work
		$this->subscriptions = array();
		$this->stompFactory = $stompFactory;
		$this->pipeManager = $pipeManager;
	}

	/**
	 * Add a subscription for the given user. If a pipe is provided, it will be notified upon success.
	 *
	 * @param User $user
	 * @param Responder $responder
	 * @return bool
	 */
	public function subscribe($user, $responder = null) {
		if($this->userHasSubscription($user)) {
			$this->pipeManager->v("User {$user->getUsername()} is already subscribed");

			// notify the pipe if it was supplied
			if($responder instanceof Responder) {
				$responder->notifyPipe(json_encode(array('subscribe' => true)));
			}

			return false;
		}

		$this->pipeManager->v('SUBSCRIBE', '+');

		try {
			$client = $this->stompFactory->createClient(array(
				'host'      => '127.0.0.1',
				'port'      => 61613,
				'vhost'     => '/',
				'login'     => 'guest',
				'passcode'  => 'guest',
			));

			$self = $this;
			$destination = $this->userSubscriptionDestination($user);

			$client->connect()->then(function($client) use ($destination, $self, $user, $responder) {
				/** @var Client $client */
				// this closure is executed when a stomp client connection is established
				$self->putSubscription($destination, new Subscription($destination, $client, $user, $self->getPipeManager()));
				// if a responder is provided, use it as a callback for success
				if($responder instanceof Responder) {
					$responder->notifyPipe(json_encode(array('subscribe' => true)));
				}
			});

			return true;
		}
		// the rabbit is probably offline
		catch(ConnectionException $e) {
			$notif = new Notif(Notif::CHANNEL_NOTIFICATION);
			$notif->critical("Cannot bind to messaging system: check your STOMP bindings and that RabbitMQ is running.");
			$this->pipeManager->v('Rabbitmq is offline or STOMP misconfigured!', '!');
			return false;
		}
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
	 * @throws SubscriptionException
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