<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Module;


use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Internal;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger;

interface ModuleNotifierInterface {

	const IMMEDIATELY = 0;
	const ONE_HOUR    = 3600;
	const ONE_DAY     = 86400;
	const ONE_WEEK    = 604800;

	/**
	 * @param IConfigLoader $configLoader
	 */
	public function __construct($configLoader);

	/**
	 * Generate a set of events to be scheduled, based on the given message.
	 *
	 * @param Internal $internalMessage - the message received on which to base our triggers.
	 * @throws ScheduleException
	 * @return Trigger[]
	 */
	public function generateTriggers($internalMessage);

	/**
	 * Return a list of callables that are associated with this trigger.
	 *
	 * @param string $trigger
	 * @return array
	 */
	public function getCallables($trigger);


	/**
	 * Generate a set of events to be scheduled by retrieving a backlog from the database
	 *
	 * @return Trigger[]
	 */
	public function spoolTriggersFromDatabase();
} 