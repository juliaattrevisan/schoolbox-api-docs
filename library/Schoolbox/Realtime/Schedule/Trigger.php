<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule;


use Alaress_Date_Format;
use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;

class Trigger {

	protected $timestamp;
	protected $notifier;
	protected $label;
	protected $model;
	protected $userId;

	/**
	 * @param Alaress_Date_Format|int $timestamp - the time at which this event is to be fired
	 * @param ModuleNotifierInterface $notifier  - an implementation of ModuleNotifierInterface and a trigger label to invoke
	 * @param string $triggerLabel               - the label associated with the triggers that will be retrieved
	 * @param object $model                      - the Model object on which the action was performed
	 * @param int $userId                        - the ID of the user that performed the action
	 */
	public function __construct($timestamp, $notifier, $triggerLabel, $model, $userId) {
		$this->timestamp = $timestamp;
		$this->notifier = $notifier;
		$this->label = $triggerLabel;
		$this->model = $model;
		$this->userId = $userId;
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
	 * Return the Model object on which this trigger is acting
	 *
	 * @return object
	 */
	public function getModel() {
		return $this->model;
	}

	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks() {
		return $this->notifier->getCallables($this->label);
	}

	/**
	 * Return the ID of the user that caused this trigger
	 *
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * Return the class of the module that this trigger is from
	 *
	 * @return ModuleNotifierInterface
	 */
	public function getModuleNotifier() {
		return $this->model;
	}

	/**
	 * Return the trigger label that this trigger is based on
	 *
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

}