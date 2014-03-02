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
use Schoolbox\Notif\Message\MessageFactory;
use Schoolbox\Notif\TopicFactory;
use Schoolbox\Realtime\Schedule\PreTrigger;
use Schoolbox\Realtime\Schedule\ScheduleException;
use Schoolbox\Role;
use Schoolbox\User;

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
	 * @throws \Schoolbox\Realtime\Schedule\ScheduleException
	 */
	public function __construct($configLoader) {
		$this->conf = $configLoader;
		$this->topics = new TopicFactory();
		$this->instantiateMapper();
	}

	/**
	 * Helper method to figure out which Mapper we apply to, and instantiate it
	 *
	 * @throws \Schoolbox\Realtime\Schedule\ScheduleException
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
	 * Generate a set of events to be scheduled by retrieving a backlog from the database
	 *
	 * @return \SplObjectStorage
	 */
	public function spoolTriggersFromDatabase() {

		// retrieve all the objects this mapper can find (yes, this will be heavy)
		$collection = $this->mapper->findAll();
		$obstore = new \SplObjectStorage();

		foreach($collection as $model) {
			// use the abstract filter method to determine what to keep or toss
			if(!$this->filter($model)) {
				continue;
			}

			// select the user we'll blame for the events we're generating
			$initiator = $this->extractInitiator($model);

			// call the extending class's trigger generator method for creation
			$events = $this->generateTriggers(Mapper::METHOD_CREATE, $model);

			// store the result of the above
			$obstore->attach(new PreTrigger($model, $initiator), $events);
		}

		// SplObjectStorage[PreTrigger : array]
		return $obstore;
	}


	/**
	 * To be implemented by extending classes.
	 * If this method returns true, the object in question will have CREATE triggers generated for it,
	 * otherwise it will be ignored.
	 *
	 * @param object $object
	 * @return bool
	 */
	protected abstract function filter($object);


	/**
	 * To be implemented by extending classes.
	 * This method should return a User object that will be considered "responsible" for the triggers that are being
	 * created for the given object. This is typically the creator of the object.
	 *
	 * @param object $object
	 * @return User
	 */
	protected abstract function extractInitiator($object);


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
