<?php
/**
 * Boolean column type - either true or false.
 *
 * For numeric values, any value equivalent to 0 is false, otherwise true.
 * For strings, any empty string (after trim) is false, otherwise true.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;

class Boolean implements IColumnType {
	protected function isBoolean($value) {
		if (is_numeric($value)) {
			return floatval($value) ? true : false;
		}
		if (is_string($value)) {
			return trim($value) ? true : false;
		}
		return $value ? true : false;
	}

	public function hydrate($value, $mapper = null) {
		return $this->isBoolean($value);
	}

	public function dehydrate($value, $mapper = null) {
		return $this->isBoolean($value);
	}
}