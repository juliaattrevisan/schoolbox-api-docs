<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox\Column\Factory as ColumnFactory;
use \Mapper as BaseMapper;
use Schoolbox\Column\IColumnType;
use Schoolbox\Column\IFactory;

class Mapper extends BaseMapper {

    /**
     * Factory for building column hydrators.
     *
     * @var IFactory
     */
    protected $column_factory;

    public function __construct() {
        parent::__construct();
    }

    protected function setup() {
        $this->setColumnFactory(new ColumnFactory());
    }

    /**
     * @param IFactory $column_factory
     *
     * @return $this
     */
    public function setColumnFactory(IFactory $column_factory) {
        $this->column_factory = $column_factory;
        return $this;
}

    /**
     * @return IFactory
     */
    public function getColumnFactory() {
        return $this->column_factory;
    }

    /**
     * Updated because hydrated objects won't usually belong to the Model class.
     * Collections will always be \Schoolbox\Collection instances for these
     * classes, though.
     *
     * @param mixed $object
     *
     * @return Collection
     */
    public function getModelAsCollection($object) {
        if ($object instanceof Collection) {
            return $object;
        }
        return new Collection(array($object));
    }

    /**
     * Gets name of get method for identifier column.
     *
     * @return string
     */
    public function getGetIdentifierMethodName() {
        $identifier = $this->getIdentifier();
        return $this->getGetMethodName($identifier['property']);
    }

    /**
     * Gets name of get method for given property.
     *
     * @param string $property
     *
     * @return string
     */
    public function getGetMethodName($property) {
        return 'get'.ucfirst($property);
    }

    /**
     * Gets name of set method for given property.
     *
     * @param string $property
     *
     * @return string
     */
    public function getSetMethodName($property) {
        return 'set'.ucfirst($property);
    }

    /**
     * Overridden to use getter and hydrator instead of getting property
     * directly.
     *
     * @param object $model
     * @param string $property
     * @param array  $map
     *
     * @return mixed
     */
    protected function getModelProperty($model, $property, $map) {
        $get_method  = $this->getGetMethodName($property);
        $value       = $model->$get_method();
        $join_mapper = isset($map['join']) ? $this->getJoinMapper($map['join']) : null;
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->dehydrate($value, $join_mapper);
        }
        return $value;
    }

    /**
     * Overridden to use setter and hydrator instead of setting property
     * directly.
     *
     * @param object $model
     * @param string $property
     * @param mixed  $value
     * @param array  $map
     *
     * @return $this
     */
    protected function setModelProperty($model, $property, $value, $map) {
        $set_method  = $this->getSetMethodName($property);
        $join_mapper = isset($map['join']) ? $this->getJoinMapper($map['join']) : null;
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->hydrate($value, $join_mapper);
        }
        $model->$set_method($value);
        return $this;
    }

    /**
     * Overrides parent::hasMap() to set the hydrator based on the field type.
     *
     * @param $map
     * @return bool
     */
    protected function hasMap($map) {
        if (is_array($map) && !isset($map['hydrator'])) {
            $map['hydrator'] = $this->column_factory->build($map['type']);
        }
        return parent::hasMap($map);
    }

    /**
     * Override base mapper to always return new collection object
     *
     * @param array $contents
     *
     * @return Collection
     */
    public function createCollection($contents = array()) {
        return new Collection($contents);
    }

	/**
	 * Sets this mapper as implementing soft deletes.
	 *
	 * @param string $column The name of the column used for storing whether a
	 *                       row is soft deleted.
	 * @param mixed  $deleted_value   The value to set the column to when a row
	 *                                is deleted, defaults to true.
	 * @param mixed  $undeleted_value The value to set the column to when a row
	 *                                is not deleted, defaults to false.
	 *
	 * @return $this
	 */
	protected function hasSoftDeleteField($column, $deleted_value = true, $undeleted_value = false) {
		//
		// TODO implement soft deletes
		// Consider true_value and false_value - true:false, null:now()
		$this->soft_delete_column          = $column;
		$this->soft_delete_deleted_value   = $deleted_value;
		$this->soft_delete_undeleted_value = $undeleted_value;
		return $this;
	}
}