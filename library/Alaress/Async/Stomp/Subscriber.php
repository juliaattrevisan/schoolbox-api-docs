<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Stomp;


use LogicException;
use React\Stomp\Client;
use React\Stomp\Exception\ConnectionException;

/**
 * Class Subscriber
 *
 * @deprecated
 * @package Alaress\Async\Stomp
 */
class Subscriber {

	/** @var Client */
	protected $stomp;

	/** @var array */
	protected $subscriptions;

    /**
     * @deprecated
     * @var SubscriptionList
     */
	protected $subscriptionList;


	/**
     * @deprecated
	 * @param Client           $stomp
	 * @param SubscriptionList $subscriptionList
	 */
	public function __construct($stomp, $subscriptionList) {
		$this->stomp = $stomp;
		$this->subscriptionList = $subscriptionList;
	}


	/**
	 * @return SubscriptionList
	 */
	public function getSubscriptionList() {
		return $this->subscriptionList;
	}


	/**
	 * Subscribes, with ACKnowledgement, to the queue provided in construction
	 *
	 * @param callable $successCallback
	 * @param callable $failureCallback
	 * @return void
	 */
	public function connect($successCallback, $failureCallback) {
		try {
			$this->stomp->connect()->then($successCallback);
			$this->stomp->on('error', $failureCallback);
		}
		catch(ConnectionException $e) {
			call_user_func($failureCallback, $e);
		}
	}


	/**
	 * @param string $queue
	 * @param callable $callback
	 * @return bool
	 */
	public function subscribe($queue, $callback) {
		if(isset($this->subscriptions[$queue])) {
			return false;
		}
		$self = $this;
		$this->subscriptions[$queue] = $this->stomp->subscribe($queue, function($frame) use ($queue, $callback, $self) {
			call_user_func($callback, $frame, $self->getSubscriptionList()->getQueueData($queue));
		});
		return true;
	}


    /**
     * @param string   $queue
     * @param callable $callback
     * @param string   $mode
     * @throws LogicException
     * @return bool
     */
	public function subscribeAck($queue, $callback, $mode = 'client-individual') {
		if(isset($this->subscriptions[$queue])) {
			return false;
		}
		$self = $this;
		$this->subscriptions[$queue] = $this->stomp->subscribeWithAck($queue, $mode, function($frame, $ackResolver) use ($queue, $callback, $self) {
			call_user_func($callback, $frame, $ackResolver, $self->getSubscriptionList()->getQueueData($queue));
		});
		return true;
	}


	/**
	 * @param callable $callback
	 * @return int the number of successfully subscribed queues
	 */
	public function subscribeAll($callback) {
		$i = 0;
		foreach($this->subscriptionList->getAllQueueNames() as $q) {
			$i += $this->subscribe($q, $callback) ? 1 : 0;
		}
		return $i;
	}


    /**
     * @param callable $callback
     * @param string   $mode
     * @throws LogicException
     * @return int the number of successfully subscribed queues
     */
	public function subscribeAllAck($callback, $mode = 'client-individual') {
		$i = 0;
		foreach($this->subscriptionList->getAllQueueNames() as $q) {
			$i += $this->subscribeAck($q, $callback, $mode) ? 1 : 0;
		}
		return $i;
	}


	/**
	 * @param string $regex
	 * @param callable $callback
	 * @return int the number of successfully subscribed queues
	 */
	public function subscribeMatching($regex, $callback) {
		$i = 0;
		foreach($this->subscriptionList->getMatchingQueueNames($regex) as $q) {
			$i += $this->subscribe($q, $callback) ? 1 : 0;
		}
		return $i;
	}


	/**
	 * @param string $queue
	 * @return bool
	 */
	public function unsubscribe($queue) {
		if(isset($this->subscriptions[$queue])) {
			$this->stomp->unsubscribe($this->subscriptions[$queue]);
			unset($this->subscriptions[$queue]);
			return true;
		}
		return false;
	}


	/**
	 * @return int the number of successfully unsubscribed queues
	 */
	public function unsubscribeAll() {
		$i = 0;
		foreach(array_keys($this->subscriptions) as $q) {
			$i += $this->unsubscribe($q) ? 1 : 0;
		}
		return $i;
	}


	/**
	 * @param string $regex
	 * @return int the number of successfully unsubscribed queues
	 */
	public function unsubscribeMatching($regex) {
		$i = 0;
		foreach($this->subscriptionList->getMatchingQueueNames($regex) as $q) {
			$i += $this->unsubscribe($q) ? 1 : 0;
		}
		return $i;
	}

}