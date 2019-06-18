<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Notification\Instance;


use SplObjectStorage;

class InstanceBundle {
    /**
     * Delivery: instant
     *
     * Set as part of a message's topic: if it's there, the message will bypass
     * routing, and be upgraded to instant delivery.
     */
    public const DELIVERY_INSTANT = 'instant';

    /**
     * Delivery: notify
     *
     * Set as part of a message's topic: if it's there, the message will be
     * routed through the standard channels.
     */
    public const DELIVERY_NOTIFY = 'notify';

	/** @var SplObjectStorage|Instance[] */
	protected $splStore;


	/**
	 * Create a new bundle
	 */
	public function __construct() {
		$this->splStore = new SplObjectStorage();
	}


	/**
	 * Binds an instance to its outer topic (something like "role.$roleId" or "service.homepage.$catId")
	 *
	 * @param string $target
	 * @param Instance $instance
	 * @param bool $force
	 */
	protected function bind($target, $instance, $force = false) {

		// If we accidentally passed a builder object (ie. forgot to call build()),
		// just accept it and build the instance on the fly.
		if($instance instanceof BuilderInterface) {
			$instance = $instance->build();
		}

		if(!$this->splStore->contains($instance)) {
			$this->splStore->attach($instance, array());
		}

		$topics = $this->splStore->offsetGet($instance);

		// detect whether or not to force-send to the user
        // (DELIVERY_INSTANT will bypass routing, DELIVERY_NOTIFY is more courteous)
		$prefix = ($force && strpos($target, 'user.') === 0)
            ? self::DELIVERY_INSTANT
            : self::DELIVERY_NOTIFY;

		// iterate the instance's topics, and prepend the given target
		foreach($instance->getTopics() as $topic) {
			$destination = "$prefix.$target.$topic";
			$topics[sha1($destination)] = $destination;
		}

		$this->splStore->attach($instance, $topics);
	}


	/**
	 * Returns the number of notification instances this bundle contains
	 * Each instance will have at least one destination topic registered to it
	 *
	 * @return int
	 */
	public function count() {
		return $this->splStore->count();
	}


	/**
	 * Returns whether or not this bundle contains notifications
	 *
	 * @return bool
	 */
	public function isEmpty() {
		return !$this->count();
	}


	/**
	 * Returns all instances contained, indexed by destination
	 *
	 * @param int $timestampOverride
	 * @return Instance[]
	 */
	public function getAll($timestampOverride = null) {
		$instances = array();
		foreach($this->splStore as $instance) {
			if(!is_null($timestampOverride)) {
				$instance->setTime($timestampOverride);
			}
			$destinations = $this->splStore->offsetGet($instance);
			foreach($destinations as $destination) {
				// index by destination
				$instances[$destination] = $instance;
			}
		}
		return $instances;
	}


} 
