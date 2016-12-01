<?php
/**
 * Collection column type - hydrates with a lazy model collection, and dehydrates those models' IDs.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Column;

use Schoolbox\GenericCollection;

class Collection implements IColumnType {

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
	 * Translates a value from a database field or other data store, into a
	 * string, integer or object value for use in a model object.
	 *
	 * @param array $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return \Schoolbox\Collection
	 */
	public function hydrate($value, $mapper = null) {

		// Figure out the identifier property to set on the model.
		$id_property = $this->getIdentifierProperty();
		$identifier_method  = isset($id_property) ?
			$mapper->getSetMethodName($id_property) :
			$mapper->getSetIdentifierMethodName();

		$collection = $mapper
            ? new GenericCollection($mapper->getModel())
            : new \Schoolbox\Collection();

		foreach((array)$value as $v) {
			$model = $mapper->createModel();
			$model->$identifier_method($v);
			$collection->add($model);
		}


		return $collection;
	}


	/**
	 * Translates a value from a value in a model object into a value for a
	 * database field or other data store.
	 *
	 * @param \Schoolbox\Collection $value
	 * @param \Schoolbox\Mapper $mapper
	 *
	 * @return array
	 */
	public function dehydrate($value, $mapper = null) {
		if (!isset($value)) {
			return null;
		}
		if (is_scalar($value) || is_array($value)) {
			return $value;
		}
		if(!($id_property = $this->getIdentifierProperty())) {
			$prop = $mapper->getIdentifier();
			$id_property = $prop['property'];
		}
		return $value->getColumnValues($id_property);
	}

} 