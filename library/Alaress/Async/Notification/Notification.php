<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Async\Notification;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Parameter\ParameterInterface;
use InvalidArgumentException;

class Notification {

	/** @var string */
	protected $hash;

	/** @var Instance[] */
	protected $instances;

	/** @var array */
	protected $params;

	/** @var array */
	protected $info;

	/**
	 * For sequencing, timestamp always matches the MOST RECENT instance contained within
	 * @var int
	 */
	protected $timestamp;


	/**
	 * Compile a notification from an array of instances
	 *
	 * @param Instance[] $instances
	 */
	public function __construct($instances) {
		$this->timestamp = 0;

		// ignore indexing
		$instances = array_values((array)$instances);

		// set up initial condition
		$this->initialise($instances[0]);
		$count = count($instances);

		// append any subsequent instances given
		for($i = 1; $i < $count; ++$i) {
			$this->appendInstance($instances[$i]);
		}
	}


	/**
	 * Setup initial condition with the instance given
	 *
	 * @param Instance $instance
	 * @return self
	 */
	protected function initialise($instance) {
		// set comparison hash
		$this->hash = $instance->getBaseHash();

		// clear existing instances
		$this->instances = array();

		// setup empty params array, indexed by token
		$this->params = array();
		foreach(array_keys($instance->getParameters()) as $token) {
			$this->params[$token] = array();
		}

		return $this->appendInstance($instance);
	}


	/**
	 * Append another instance to this notification
	 *
	 * @param Instance $instance
	 * @throws InvalidArgumentException
	 * @return self
	 */
	public function appendInstance($instance) {
		if($instance->getBaseHash() != $this->hash) {
			throw new InvalidArgumentException("Instance to append does not match initial hash");
		}
		$paramHash = $instance->getParamHash();
		if(isset($this->instances[$paramHash])) {
			// we already have this instance, dismiss
			return $this;
		}

		// store instance
		$this->instances[$paramHash] = $instance;

		// take timestamp if it's more recent than the one we have already
		if($instance->getTime() > $this->timestamp) {
			$this->timestamp = $instance->getTime();

			// cache basic information
			$array = $instance->jsonSerialize();
			$this->info = array(
				'title' => $array['title']['text'],
				'namespace' => $array['namespace'],
				'icon' => $array['icon'],
				'href' => $array['href'],
				'meta' => $array['meta']
			);
		}

		$this->timestamp = max($instance->getTime(), $this->timestamp);


		// collate params with existing
		foreach($instance->getParameters() as $token => $param) {
			switch($param->getMode()) {
				// replace existing
				case ParameterInterface::STACKING_REPLACE:
					$this->params[$token] = array($instance->getTime() => $param);
					break;

				// append to existing, indexed by time
				case ParameterInterface::STACKING_APPEND:
					$this->params[$token][$instance->getTime()] = $param;
					break;

				// already set, so only set IF NOT ALREADY SET
				case ParameterInterface::STACKING_IGNORE:
					if(!count($this->params[$token])) {
						$this->params[$token] = array($instance->getTime() => $param);
					}
			}
		}

		return $this;
	}


	/**
	 * @return string
	 */
	public function getId() {
		return $this->hash;
	}


	/**
	 * @return int
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}


	/**
	 * Get the :tokenised string of this notification
	 *
	 * @return string
	 */
	public function getTitle() {
		return $this->info['title'];
	}


	/**
	 * @return string
	 */
	public function getNamespace() {
		return $this->info['namespace'];
	}


	/**
	 * @return string
	 */
	public function getIcon() {
		return $this->info['icon'];
	}


	/**
	 * @return string
	 */
	public function getHref() {
		return $this->info['href'];
	}


	/**
	 * @param string $field
	 * @return string|array
	 */
	public function getMeta($field = null) {
		if($field) {
			return isset($this->info['meta'][$field]) ? $this->info['meta'][$field] : '';
		}
		return $this->info['meta'];
	}


	/**
	 * @return string
	 */
	public function getBody() {
		return $this->getMeta('body');
	}


	/**
	 * @return string
	 */
	public function getByline() {
		return $this->getMeta('byline');
	}


	/**
	 * @return array
	 */
	public function getParameters() {
		return $this->params;
	}


    /**
     * @return int[]
     */
	public function getEffectorIds()
    {
        return array_filter(
            array_map(function (Instance $instance) {
                return isset($instance->getContext()['user'])
                    ? $instance->getContext()['user']
                    : null;
            }, $this->instances)
        );
    }

} 