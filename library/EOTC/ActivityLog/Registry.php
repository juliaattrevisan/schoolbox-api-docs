<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ActivityLog;


class Registry {

	/**
	 * A Collection of observers
	 *
	 * @var array
	 */
	private $registry = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setRegistry(new \Schoolbox\Collection());
	}

	/**
	 * Delegation method
	 *
	 * @param $item
	 */
	public function add($item) {
		$this->getRegistry()->add($item);
	}

	/**
	 * All observers - notify!
	 */
	public function notify() {
		foreach($this->getRegistry() as $current) {
			$current->notify();
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set registry
	 *
	 * @param array $registry
	 */
	private function setRegistry($registry) {
		$this->registry = $registry;
	}

	/**
	 * Get registry
	 *
	 * @return array
	 */
	private function getRegistry() {
		return $this->registry;
	}
}