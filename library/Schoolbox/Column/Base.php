<?php
/**
 * Most basic column type - doesn't perform any translation on hydration or
 * dehydration.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;

class Base implements IColumnType {
	public function hydrate($value, $mapper = null) {
		return $value;
	}

	public function dehydrate($value, $mapper = null) {
		return $value;
	}
}