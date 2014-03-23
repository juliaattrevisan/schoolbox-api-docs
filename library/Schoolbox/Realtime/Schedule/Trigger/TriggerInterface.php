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

interface TriggerInterface {

	/**
	 * Return the timestamp at which this trigger should fire (can be in the past)
	 *
	 * @return int
	 */
	public function getTimestamp();

	/**
	 * Return the Model object on which this trigger is acting
	 *
	 * @return object
	 */
	public function getModel();

	/**
	 * Return an array of callables that are appropriate for this trigger
	 *
	 * @return callable[]
	 */
	public function getCallbacks();

	/**
	 * Return the ID of the user that caused this trigger
	 *
	 * @return User
	 */
	public function getUser();

	/**
	 * Return the class of the module that this trigger is from
	 *
	 * @return ModuleNotifierInterface
	 */
	public function getModuleNotifier();

	/**
	 * Return the trigger label that this trigger is based on
	 *
	 * @return string
	 */
	public function getLabel();

	/**
	 * Return the diff of the object represented by this trigger, or null if it's new
	 *
	 * @return array|null
	 */
	public function getDiff();

} 