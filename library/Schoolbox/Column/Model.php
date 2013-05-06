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

	/**
	 * The field name to get from the associated model when calling dehydrate().
	 * If not set, then the model's identifier is retrieved.
	 *
	 * @var string
	 */
	protected $field_name;

	/**
	 * @param  string $field_name
	 *
	 * @return $this
	 */
	public function setFieldName($field_name) {
		$this->field_name = $field_name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFieldName() {
		return $this->field_name;
	}

	public function hydrate($value, $mapper = null) {
		return new LazyModel($mapper, $value);
	}

	/**
	 * @param mixed             $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return mixed
	 * * If $value is a scalar value, returns $value.
	 * * If $this->setFieldName() was called, returns the value of that field
	 *   from $value
	 * * Otherwise, returns ID of $value
	 */
	public function dehydrate($value, $mapper = null) {
		//
		// Did someone set this as a scalar? They probably meant to set an ID.
		// Might as well allow this.
		if (is_scalar($value)) {
			return $value;
		}
		$identifier_method = isset($this->field_name) ?
			$mapper->getGetMethodName($this->field_name):
			$mapper->getGetIdentifierMethodName();
		return $value->$identifier_method();
	}
}