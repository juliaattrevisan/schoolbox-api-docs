<?php
/**
 * Alaress_Cache_Locker_Log
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Cache_Locker_Log {

	/**
	 * Show plain debugging messages
	 *
	 * @var bool
	 */
	private $verbose = false;

	/**
	 * Constructor
	 *
	 * @param $verbosity
	 */
	public function __construct($verbosity) {
		$this->setVerbose($verbosity);
	}

	/**
	 * Logging mechanism
	 *
	 * @param $text
	 */
	public function report($text) {
		if ($this->getVerbose()) {
			echo $text . "<BR>\n";
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set verbose
	 *
	 * @param boolean $verbose
	 * @return \Alaress_Cache_Locker_Abstract
	 */
	private function setVerbose($verbose) {
		$this->verbose = $verbose;

		return $this;
	}

	/**
	 * Get verbose
	 *
	 * @return boolean
	 */
	private function getVerbose() {
		return $this->verbose;
	}
}
