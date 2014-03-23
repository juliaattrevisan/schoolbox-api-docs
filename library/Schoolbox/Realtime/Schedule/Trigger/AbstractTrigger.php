<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Trigger;


use Schoolbox\Realtime\Schedule\Module\ModuleNotifierInterface;
use Schoolbox\User;

abstract class AbstractTrigger implements TriggerInterface {

	protected $notifier;
	protected $label;
	protected $model;
	protected $user;

	/**
	 * Return the Model object on which this trigger is acting
	 *
	 * @return object
	 */
	public function getModel() {
		return $this->model;
	}

	/**
	 * Return the ID of the user that caused this trigger
	 *
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Return the class of the module that this trigger is from
	 *
	 * @return ModuleNotifierInterface
	 */
	public function getModuleNotifier() {
		return $this->notifier;
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