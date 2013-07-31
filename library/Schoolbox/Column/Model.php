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
	 * The property to set on the model when hydrating, or get when dehydrating.
	 * If not set, then the mapper's ID property is used.
	 *
	 * @var string
	 */
	protected $identifier_property;

	/**
	 * @param  string $dehydrate_property
	 *
	 * @return $this
	 */
	public function setIdentifierProperty($dehydrate_property) {
		$this->identifier_property = $dehydrate_property;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getIdentifierProperty() {
		return $this->identifier_property;
	}

	/**
	 * Hydrates this column with a model which is empty, except for its
	 * identifier property.
	 *
	 * @example hydrating "2" with the UserMapper creates a new User object,
	 *          with its ID property set to 2.
	 *          If setIdentifierProperty() was called on this column object, that
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
		$id_property = $this->getIdentifierProperty();
		$identifier_method  = isset($id_property) ?
			$mapper->getSetMethodName($id_property):
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
	 * * If $this->setIdentifierProperty() was called, returns the value of that
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
		$id_property = $this->getIdentifierProperty();
		$identifier_method  = isset($id_property) ?
			$mapper->getGetMethodName($id_property):
			$mapper->getGetIdentifierMethodName();
		return $value->$identifier_method();
	}
}