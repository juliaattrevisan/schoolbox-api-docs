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

	public function hydrate($value, $mapper = null) {
		return new LazyModel($mapper, $value, $this->getHydrateColumn(), $this->getDehydrateProperty());
	}

	/**
	 * @param mixed             $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return mixed
	 * * If $value is a scalar value, returns $value.
	 * * If $this->setDehydrateProperty() was called, returns the value of that
	 *   field from $value
	 * * Otherwise, returns ID of $value
	 */
	public function dehydrate($value, $mapper = null) {
		//
		// Did someone set this as a scalar? They probably meant to set an ID.
		// Might as well allow this.
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