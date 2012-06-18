<?php
/**
 * Leave_Notice_Abstract_Factory
 *
 * Abstract Factory Method. This used by the _User and _Parent factories for notifications
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Leave_Notice_Abstract_Factory {
	protected $request = null;

	/**
	 * Constructor
	 *
	 * @param $request
	 */
	public function __construct($request) {
		$this->setRequest($request);
	}

	/**
	 * Produce a Notice object whose implementation is school-specific
	 */
	abstract public function produce();

	/* Getters/Setters
	 */

	/**
	 * @param $request
	 */
	protected function setRequest($request) {
		$this->request = $request;
	}

	/**
	 * @return null
	 */
	protected function getRequest() {
		return $this->request;
	}
}
