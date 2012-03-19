<?php
/**
 * Leave_Notice_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Leave_Notice_Abstract {
	protected $request = null;

	/**
	 * Constructor
	 *
	 * @param Leave_Request $request
	 */
	public function __construct(Leave_Request $request) {
		$this->setRequest($request);

		$this->setup();
	}

	abstract public function setup();
	abstract public function send();

	/* Getters/Setters
	 */

	/**
	 * Set request
	 *
	 * @param $request
	 */
	private function setRequest($request) {
		$this->request = $request;
	}

	/**
	 * Get request
	 *
	 * @return null
	 */
	protected function getRequest() {
		return $this->request;
	}
}
