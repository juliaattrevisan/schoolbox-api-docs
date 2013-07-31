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
use Schoolbox\Mapper;

class Model implements IColumnType {

	/**
	 * The database column name to pull the value from when calling hydrate().
	 * If not set, then the database's identifier is retrieved.
	 *
	 * @var string
	 */
	protected $hydrate_column;

	/**
	 * The model property name to get the value from when calling dehydrate().
	 * If not set, then the model's ID property is retrieved.
	 *
	 * @var string
	 */
	protected $dehydrate_property;

	/**
	 * The field name to get from the associated model when calling dehydrate().
	 * If not set, then the model's identifier is retrieved.
	 *
	 * @var string
	 */
	protected $field_name;

	/**
	 * @param  string $dehydrate_property
	 *
	 * @return $this
	 */
	public function setDehydrateProperty($dehydrate_property) {
		$this->dehydrate_property = $dehydrate_property;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDehydrateProperty() {
		return $this->dehydrate_property;
	}

	/**
	 * @param  string $hydrate_column
	 *
	 * @return $this
	 */
	public function setHydrateColumn($hydrate_column) {
		$this->hydrate_column = $hydrate_column;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getHydrateColumn() {
		return $this->hydrate_column;
	}

	/**
	 * Hydrates this column with a model which is empty, except for its
	 * identifier property.
	 *
	 * @example hydrating "2" with the UserMapper creates a new User object,
	 *          with its ID property set to 2.
	 *          If setDehydrateProperty() was called on this column object, that
	 *          property will be hydrated instead.
	 *
	 * @param mixed  $value
	 * @param Mapper $mapper
	 *
	 * @return object
	 */
	public function hydrate($value, $mapper = null) {
		/** @var Mapper $mapper */
		$model = $mapper->createModel();
		//
		// Figure out the identifier property to set on the model.
		$dehydrate_property = $this->getDehydrateProperty();
		$identifier_method  = isset($dehydrate_property) ?
			$mapper->getSetMethodName($dehydrate_property):
			$mapper->getSetIdentifierMethodName();
		$model->$identifier_method($value);
		return $model;
	}

	/**
	 * @param mixed             $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return mixed
	 * * If $value is null, returns null (represents a missing foreign key).
	 * * If $value is a scalar value, returns $value. (user probably meant to
	 *   set an ID value - might as well allow this)
	 * * If $this->setDehydrateProperty() was called, returns the value of that
	 *   field from $value
	 * * Otherwise, returns ID of $value
	 */
	public function dehydrate($value, $mapper = null) {
		if (!isset($value)) {
			return null;
		}
		if (is_scalar($value)) {
			return $value;
		}
		$dehydrate_property = $this->getDehydrateProperty();
		$identifier_method  = isset($dehydrate_property) ?
			$mapper->getGetMethodName($dehydrate_property):
			$mapper->getGetIdentifierMethodName();
		return $value->$identifier_method();
	}
}