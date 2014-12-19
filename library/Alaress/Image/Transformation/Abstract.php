<?php
/**
 * Alaress_Image_Transformation_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Transformation_Abstract {
	protected $allTransformations = array();

	/**
	 * Get all transformations for a particular key
	 *
	 * @param  $key
	 * @throws Exception_Schoolbox if transformation isn't found
	 * @return array
	 */
	public function retrieve($key) {
		if (array_key_exists($key, $this->getAllTransformations())) {
			return $this->allTransformations[$key];
		}

		throw new Exception_Schoolbox('Could not load transformation list.');
	}

	/**
	 * Get all transformations
	 *
	 * @return array
	 */
	protected function getAllTransformations() {
		return $this->allTransformations;
	}

	/**
	 * Get a list of transformation keys
	 *
	 * @return array
	 */
	public function getAllTransformationNames() {
		return array_keys($this->getAllTransformations());
	}
}
