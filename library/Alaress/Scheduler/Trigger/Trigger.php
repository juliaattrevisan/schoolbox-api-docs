<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Schedule\Trigger;


use Alaress_Date_Format;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\User;

class Trigger extends AbstractTrigger {

	protected $timestamp;
	protected $diff;

	/**
	 * @param Alaress_Date_Format|int $timestamp - the time at which this event is to be fired
	 * @param ModuleNotifierInterface $notifier  - an implementation of ModuleNotifierInterface and a trigger label to invoke
	 * @param string $triggerLabel               - the label associated with the triggers that will be retrieved
	 * @param object $model                      - the Model object on which the action was performed
	 * @param User $user                         - the User that performed the action
	 * @param array $diff                        - an array representing how the object has changed, or null
	 */
	public function __construct($timestamp, $notifier, $triggerLabel, $model, $user, $diff = null) {
		$this->timestamp = $timestamp;
		$this->notifier = $notifier;
		$this->label = $triggerLabel;
		$this->model = $model;
		$this->user = $user;
		$this->diff = $diff;
	}

	/**
	 * Return the timestamp at which this trigger should fire (can be in the past)
	 *
	 * @return int
	 */
	public function getTimestamp() {
		if($this->timestamp instanceof Alaress_Date_Format) {
			return $this->timestamp->getUnixTimestamp();
		}
		return (int)$this->timestamp;
	}

	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks() {
		return $this->notifier->retrieveCallbacks($this->label);
	}

	/**
	 * Return the diff of the object represented by this trigger, or null if it's new
	 *
	 * @return array|null
	 */
	public function getDiff() {
		return $this->diff;
	}

}