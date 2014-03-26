<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\WorkerCrew\Transaction;


use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;

class Transaction {

	/** @var LoopInterface */
	protected $loop;

	/** @var callable */
	protected $callback;

	/** @var int */
	protected $cacheInterval;

	/** @var TimerInterface */
	protected $timer = null;


	/**
	 * Create a new Transaction
	 *
	 * @param LoopInterface $loop
	 * @param callable $callback
	 * @param int $cacheInterval - how long to cache for, in seconds
	 */
	function __construct($loop, $callback, $cacheInterval = 1) {
		$this->loop = $loop;
		$this->callback = $callback;
		$this->cacheInterval = $cacheInterval;
	}


	/**
	 * Set the caching interval
	 *
	 * @param int $cacheInterval
	 * @return self
	 */
	public function setCacheInterval($cacheInterval) {
		$this->cacheInterval = $cacheInterval;
		return $this;
	}


	/**
	 * Get the caching interval
	 *
	 * @return int
	 */
	public function getCacheInterval() {
		return $this->cacheInterval;
	}


	/**
	 * Set data in this transaction
	 *
	 * @param string|int $key
	 * @param mixed $value
	 * @return self
	 */
	public function put($key, $value) {
		if(!$this->isTicking()) {
			$this->resetTimer();
		}
		$data = $this->timer->getData() ?: array();
		$data[$key] = $value;
		$this->timer->setData($data);
		return $this;
	}


	/**
	 * Reset the timer, clearing a pre-existing one if it exists
	 */
	protected function resetTimer() {
		if(isset($this->timer)) {
			$this->timer->cancel();
		}
		$this->timer = $this->loop->addTimer($this->cacheInterval, $this->callback);
	}


	/**
	 * Returns whether or not this transaction is currently active and pending execution
	 *
	 * @return bool
	 */
	public function isTicking() {
		return isset($this->timer) && $this->timer->isActive();
	}

}