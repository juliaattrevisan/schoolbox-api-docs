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

	/**
	 * @param Factory $stompFactory
	 * @param PipeManager $pipeManager
	 */
	public function __construct($stompFactory, $pipeManager) {
		// this factory requires its own loop to work
		$this->subscriptions = array();
		$this->stompFactory = $stompFactory;
		$this->pipeManager = $pipeManager;
	}

	/**
	 * Add a subscription for the given user. If a pipe is provided, it will be notified upon success.
	 *
	 * @param int $userId
	 * @param Responder $responder
	 * @return bool
	 */
	public function subscribe($userId, $responder = null) {
		if($this->userHasSubscription($userId)) {
			$this->pipeManager->v("User $userId is already subscribed");

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
			$destination = $this->userSubscriptionDestination($userId);

			$client->connect()->then(function($client) use ($destination, $self, $userId, $responder) {
				/** @var Client $client */
				// this closure is executed when a stomp client connection is established
				$self->putSubscription($destination, new Subscription($destination, $client, $userId, $self->getPipeManager()));
				// if a responder is provided, use it as a callback for success
				if($responder instanceof Responder) {
					$responder->notifyPipe(json_encode(array('subscribe' => true)));
				}
			});

			return true;
		}
		// the rabbit is probably offline
		catch(ConnectionException $e) {
			$notif = new Notif(Notif::TOPIC_NOTIFICATION);
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
		return "/amq/queue/u.$userId.unread";
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