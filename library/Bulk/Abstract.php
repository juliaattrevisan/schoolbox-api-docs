<?php
/**
 * Bulk_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Bulk_Abstract {
	protected $file = null;

	/**
	 * Constructor
	 *
	 * @param $file
	 */
	public function __construct($file) {
		$this->setFile($file);
	}


	/* Getters/Setters
	 */

	/**
	 * Set file
	 *
	 * @param $file
	 */
	protected function setFile($file) {
		$this->file = $file;
	}

	/**
	 * Get file
	 *
	 * @return null
	 */
	protected function getFile() {
		return $this->file;
	}
}
