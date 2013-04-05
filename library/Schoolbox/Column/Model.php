<?php
/**
 * Model column type - hydrates with a lazy model, and dehydrates that model's
 * ID.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Column;
use Schoolbox\LazyModel;

class Model implements IColumnType {
	public function hydrate($value, $mapper = null) {
		return new LazyModel($mapper, $value);
	}

	public function dehydrate($value, $mapper = null) {
		//
		// Did someone set this as a scalar? They probably meant to set an ID.
		// Might as well allow this.
		if (is_scalar($value)) {
			return $value;
		}
		$identifier_method = $mapper->getGetIdentifierMethodName();
		return $value->$identifier_method();
	}
}