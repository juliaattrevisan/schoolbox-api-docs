<?php
/**
 * Base interface for column type classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column;

interface IColumnType {
	/**
	 * Translates a value from a database field or other data store, into a
	 * string, integer or object value for use in a model object.
	 *
	 * @param string            $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return mixed
	 */
	public function hydrate($value, $mapper = null);

	/**
	 * Translates a value from a value in a model object into a value for a
	 * database field or other data store.
	 *
	 * @param mixed             $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return string
	 */
	public function dehydrate($value, $mapper = null);
}