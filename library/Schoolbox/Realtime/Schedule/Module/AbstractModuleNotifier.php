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

	/** @var callable[] */
	protected $closures = array();

	/** @var IConfigLoader */
	protected $conf;

	/** @var TopicFactory */
	protected $topics;

	/** @var Mapper */
	protected $mapper;


	/**
	 * @param IConfigLoader $configLoader
	 * @throws ScheduleException
	 */
	public function __construct($configLoader) {
		$this->conf = $configLoader;
		$this->topics = new TopicFactory();
		$this->instantiateMapper();
	}


	/**
	 * Helper method to figure out which Mapper we apply to, and instantiate it
	 *
	 * @throws ScheduleException
	 */
	protected function instantiateMapper() {
		$mapperClass = str_replace('Notifier', 'Mapper', get_called_class());

		if(!class_exists($mapperClass)) {
			throw new ScheduleException("Could not find class '$mapperClass'");
		}
		$m = new $mapperClass();
		if(!$m instanceof Mapper) {
			throw new ScheduleException("Class '$mapperClass' must be an instance of Mapper");
		}

		$this->mapper = $m;
	}


	/**
	 * Return the list of callbacks that are associated with this trigger.
	 *
	 * @param $label
	 * @return array
	 */
	public function retrieveCallbacks($label) {

		$callables = array();

		foreach(array(Role::STUDENT, Role::PARENT, Role::STAFF) as $role) {
			$method = sprintf("notify%s%s", ucfirst($role), ucfirst($label));
			if(method_exists($this, $method)) {
				$callables[$role] = array($this, $method);
			}
		}

		return $callables;
	}


	/**
	 * String representation of object
	 *
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return get_class($this->conf);
	}


	/**
	 * Constructs the object
	 * @param string $serialized the string representation of the object.
	 * @return void
	 */
	public function unserialize($serialized) {
		// the only thing we serialize is the name of the config class
		$this->conf = new $serialized();
		$this->topics = new TopicFactory();
		$this->instantiateMapper();
		$this->loadDependencies();
	}

}
