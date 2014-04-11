<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Schedule\Module;


use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\Message\Messenger;
use Schoolbox\Notif\TopicFactory;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\Source;
use Schoolbox\Realtime\Message\SourceMapper;
use Schoolbox\Realtime\Schedule\ScheduleException;

abstract class AbstractModuleNotifier implements ModuleNotifierInterface {

	/** @var callable[] */
	protected $closures = array();

	/** @var IConfigLoader */
	protected $conf;

	/** @var TopicFactory */
	protected $topics;

	/** @var Mapper */
	protected $mapper;

	/** @var NS */
	protected $namespace;


	/**
	 * @param IConfigLoader $configLoader
	 * @throws ScheduleException
	 */
	public function __construct($configLoader) {
		$this->conf = $configLoader;
		$this->topics = new TopicFactory();
		$this->instantiateMapper();
		$this->loadDependencies();
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
	 * Uses SQL lookup to determine code paths. Dirty and awful, but at the moment the best way forward.
	 *
	 * @param string $label
	 * @throws ScheduleException
	 * @return array
	 */
	public function retrieveCallbacks($label) {
		/** @var SourceMapper $m */
		$m = SourceMapper::getInstance();

		if(!$this->namespace instanceof NS) {
			throw new ScheduleException("Malformed namespace in ".get_called_class());
		}

		/** @var Collection|Source[] $sourceCollection */
		$sourceCollection = $m->findByColumnList(array(
			'namespace' => $this->namespace->getId(),
			'key'       => $label
		));

		$callbacks = array();

		foreach($sourceCollection as $source) {
			$method = sprintf("notify%s%s",
				ucfirst(strtolower($source->getRoleType())),
				ucfirst(strtolower($source->getKey()))
			);
			if(method_exists($this, $method)) {
				$callbacks[] = array($this, $method);
			}
		}
		return $callbacks;
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
		$this->__construct(new $serialized());
	}

	/**
	 * Return the message namespace this module conforms to.
	 *
	 * @return NS
	 */
	public function getNamespace() {
		return $this->namespace;
	}


}
