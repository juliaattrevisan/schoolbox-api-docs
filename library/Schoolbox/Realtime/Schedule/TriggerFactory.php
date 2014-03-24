<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Schoolbox\Notif\Message\Internal;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\Realtime\Schedule\Trigger\CancelTrigger;
use Schoolbox\Realtime\Schedule\Trigger\Trigger;
use Schoolbox\User;

class TriggerFactory {

	/** @var ModuleNotifierInterface */
	protected $notifier;

	/** @var object */
	protected $model;

	/** @var User */
	protected $user;

	/** @var array */
	protected $diff;

	/** @var TriggerBundle[] */
	protected $bundles;

	/**
	 * @param ModuleNotifierInterface $instance
	 * @param Internal $message
	 */
	public function __construct($instance, $message) {
		$this->notifier = $instance;
		$this->model = $message->getObject();
		$this->user = $message->getInitiator();
		$this->diff = $message->getDiff();
		$this->bundles = array();
	}

	/**
	 * @param string $method
	 * @return TriggerBundle
	 */
	public function buildTriggers($method) {
		$this->notifier->bundleTriggers($this->getBundle($method));
		return $this->getBundle($method);
	}

	/**
	 * @param array $events
	 * @param bool $disregardExpired
	 * @return Trigger[]
	 */
	public function build($events, $disregardExpired = false) {
		$triggers = array();
		$now = time();

		// iterate across the events supplied and construct triggers out of them
		foreach($events as $label => $time) {
			$t = new Trigger($time, $this->notifier, $label, $this->model, $this->user, $this->diff);

			// if the timestamp is in the past, and we're disregarding expired, do not include in the return
			if(!($disregardExpired && $t->getTimestamp() < $now)) {
				$triggers[] = $t;
			}
		}
		return $triggers;
	}

	/**
	 * @param $events
	 * @return CancelTrigger[]
	 */
	public function cancel($events) {
		$triggers = array();
		foreach($events as $label) {
			$triggers[] = new CancelTrigger($this->notifier, $label, $this->model, $this->user);
		}
		return $triggers;
	}

	/**
	 * @param string $method
	 * @return TriggerBundle
	 */
	public function getBundle($method) {
		$method = strtolower($method);
		if(!isset($this->bundles[$method])) {
			$this->bundles[$method] = new TriggerBundle($this, $method);
		}
		return $this->bundles[$method];
	}

	/**
	 * @return array
	 */
	public function getDiff() {
		return $this->diff;
	}

	/**
	 * @return object
	 */
	public function getModel() {
		return $this->model;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
		return $this->user;
	}


} 