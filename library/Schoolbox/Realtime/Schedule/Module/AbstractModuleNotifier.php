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
use Schoolbox\Notif\TopicFactory;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Role;

abstract class AbstractModuleNotifier implements ModuleNotifierInterface {


	protected $closures = array();

	protected $conf;

	protected $topics;

	/**
	 * @param IConfigLoader $configLoader
	 */
	public function __construct($configLoader) {
		$this->conf = $configLoader;
		$this->topics = new TopicFactory();
	}


	/**
	 * Add a callable for a role, based on a specific trigger
	 *
	 * @param string $roleType
	 * @param string $trigger
	 * @param callable $callable
	 * @throws ScheduleException
	 * @return void
	 */
//	public function registerCallback($roleType, $trigger, $callable = null) {
//
//		// if no callable provided,
//		if(is_null($callable)) {
//			$cls = get_called_class();
//			$method = sprintf("%s%s%s",
//				lcfirst(substr($cls, strrpos($cls, '\\')+1)),
//				ucfirst(strtolower($trigger)),
//				ucfirst(strtolower($roleType))
//			);
//
//			if(method_exists($this, $method)) {
//				$callable = array($this, $method);
//			}
//			else {
//				throw new ScheduleException("No valid method provided for $cls($roleType => $trigger): expected '$method'.");
//			}
//		}
//		if(!is_callable($callable)) {
//			throw new ScheduleException("Invalid method provided for $roleType => $trigger.");
//		}
//
//		$this->closures[$trigger][$roleType] = $callable;
//	}


	/**
	 * Retrieve all the callables associated with a particular trigger (one per role, if it exists)
	 *
	 * @param $trigger
	 * @return array
	 */
	public function getCallables($trigger) {

		$callables = array();

		foreach(array(Role::STUDENT, Role::PARENT, Role::STAFF) as $role) {
			$method = sprintf("notify%s%s", ucfirst($role), ucfirst($trigger));
			if(method_exists($this, $method)) {
				$callables[$role] = array($this, $method);
			}
		}

		return $callables;
	}



} 