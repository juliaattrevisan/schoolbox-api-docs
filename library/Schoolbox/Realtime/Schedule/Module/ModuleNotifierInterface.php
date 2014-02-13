<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Module;


use Alaress_Date_Format;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\Internal;
use Schoolbox\Notif\Message\MessageFactory;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Realtime\Schedule\Trigger;
use Schoolbox\User;

interface ModuleNotifierInterface {

	const IMMEDIATELY = 0;
	const ONE_HOUR    = 3600;
	const ONE_DAY     = 86400;
	const ONE_WEEK    = 604800;

	/**
	 * @param IConfigLoader $configLoader
	 * @param MessageFactory $messageFactory
	 */
	public function __construct($configLoader, $messageFactory);


	/*
	 * @param User $user            the User that performed/initiated the operation
	 * @param array $diff           if $method is 'UPDATE', an array of the changes made to the object, null otherwise
	 */

	/**
	 * Generate a set of triggers to be fired at specific times, based on the given data.
	 *
	 * @param string $method                the CRUD operation that was performed on the object
	 * @param object $object                the Mappable object that was operated on
	 * @throws ScheduleException            will be thrown if the CRUD operation is not recognised
	 * @return Alaress_Date_Format[]|int[]  return an array of ADFs or timestamps, indexed by trigger label
	 */
	public function generateTriggers($method, $object);

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
	 * @return Alaress_Date_Format[]|int[]
	 */
	public function spoolTriggersFromDatabase();
} 