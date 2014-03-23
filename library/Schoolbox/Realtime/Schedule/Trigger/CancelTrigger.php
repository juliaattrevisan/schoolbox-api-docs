<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


/**
 * A specific type of trigger to unset a previously-scheduled event
 *
 * Class CancelTrigger
 * @package Schoolbox\Realtime\Schedule\Trigger
 */
class CancelTrigger extends AbstractTrigger {

	public function __construct($notifier, $triggerLabel, $model, $user) {
		$this->notifier = $notifier;
		$this->label = $triggerLabel;
		$this->model = $model;
		$this->user = $user;
	}

	/**
	 * Return the timestamp at which this trigger should fire (can be in the past)
	 *
	 * @return int
	 */
	public function getTimestamp() {
		return null;
	}

	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks() {
		return array();
	}

	/**
	 * Return the diff of the object represented by this trigger, or null if it's new
	 *
	 * @return array|null
	 */
	public function getDiff() {
		return null;
	}

} 