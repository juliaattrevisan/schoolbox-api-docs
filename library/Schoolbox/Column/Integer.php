<?php
/**
 * Integer column type.
 *
 * Accepts anything recognized by is_numeric, and converts to integer as best
 * it can.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;

class Integer implements IColumnType {
	protected function convert($value) {
		if (is_numeric($value)) {
			//
			// Is this a
			if (preg_match('/\\./', $value)) {
				return intval(floatval($value));
			}
			return intval($value);
		}
		return null;
	}

	public function hydrate($value, $mapper = null) {
		return $this->convert($value);
	}

	public function dehydrate($value, $mapper = null) {
		return $this->convert($value);
	}
}