<?php
/**
 * Decimal number column type, with optional number of decimal places.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

class Decimal implements IColumnType {
	/**
	 * @var int
	 */
	protected $places = 0;

	/**
	 * @param  int $places
	 * @return $this
	 */
	public function setPlaces($places) {
		$this->places = $places;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getPlaces() {
		return $this->places;
	}

	public function hydrate($value, $mapper = null) {
		// number format gets upset about empty strings
		if ($value === '') {
			$value = null;
		}
		return isset($value) ? number_format($value, $this->places, '.', '') : null;
	}

	public function dehydrate($value, $mapper = null) {
		// number format gets upset about empty strings
		if ($value === '') {
			$value = null;
		}
		return isset($value) ? number_format($value, $this->places, '.', '') : null;
	}
}
