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

    /**
     * Factory for configuration loading.
     *
     * @var IConfigLoader
     */
    protected $config_loader;

    /**
     * Overrides constructor as public, so that new instances can be created
     * without going through getInstance().
     */
    public function __construct() {
        parent::__construct();
    }

	/**
	 * The column that is used for storing if this field has been soft deleted.
	 *
	 * @var string
	 */
	protected $soft_delete_column = null;

	/**
	 * The value for the soft delete column when the row is soft deleted.
	 *
	 * @var mixed
	 */
	protected $soft_delete_deleted_value = null;

	/**
	 * The value for the soft delete column when the row is not deleted.
	 *
	 * @var mixed
	 */
	protected $soft_delete_undeleted_value = null;

    protected function setup() {
        $this->setConfigLoader(new ConfigLoader());
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
     * @param  \Schoolbox\IConfigLoader $config_loader
     *
     * @return $this
     */
    public function setConfigLoader($config_loader) {
        $this->config_loader = $config_loader;
        return $this;
    }

    /**
     * @return \Schoolbox\IConfigLoader
     */
    public function getConfigLoader() {
        return $this->config_loader;
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
        if ($object instanceof \Collection) {
            return new Collection($object->toArray());
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
	 * Sets this mapper as implementing soft deletes, as a time that that row
	 * was deleted, or null if it hasn't been.
	 *
	 * @param string $column The name of the column used for storing whether a
	 *                       row is soft deleted.
	 *
	 * @return $this
	 */
	protected function hasSoftDeleteBooleanField($column) {
		return $this->hasSoftDeleteField($column, true, false);
	}

	/**
	 * Sets this mapper as implementing soft deletes, as a time that that row
	 * was deleted, or null if it hasn't been.
	 *
	 * @param string $column The name of the column used for storing whether a
	 *                       row is soft deleted.
	 *
	 * @return $this
	 */
	protected function hasSoftDeleteDateField($column) {
		return $this->hasSoftDeleteField($column, date('Y-m-d H:i:s'), null);
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
		$this->soft_delete_column          = $column;
		$this->soft_delete_deleted_value   = $deleted_value;
		$this->soft_delete_undeleted_value = $undeleted_value;
		return $this;
	}

	/**
	 * Overrides delete() method to call softDelete if a soft delete field was
	 * set up, or parent's delete() method if it wasn't.
	 *
	 * @param $object
	 *
	 * @return bool
	 */
	public function delete($object) {
		if (isset($this->soft_delete_column)) {
			return $this->softDelete($object);
		}
		else {
			return $this->hardDelete($object);
		}
	}

	/**
	 * Soft deletes one or more models.
	 *
	 * @param object $object Model or collection of models to delete
	 *
	 * @return bool
	 */
	public function softDelete($object) {
		if (!isset($this->soft_delete_column)) {
			return false;
		}
		return $this->updateAll(
			$object,
			$this->soft_delete_column,
			$this->soft_delete_deleted_value,
			'preSoftDelete',
			'postSoftDelete'
		);
	}

	/**
	 * Alias for parent's delete() method.
	 *
	 * @param object $object Model or collection of models to delete
	 *
	 * @return bool
	 */
	public function hardDelete($object) {
		return parent::delete($object);
	}

	/**
	 * Restores one or more models that were soft deleted.
	 *
	 * @param object $object Model or collection of models to restore
	 *
	 * @return bool
	 */
	public function restoreSoftDeleted($object) {
		if (!isset($this->soft_delete_column)) {
			return false;
		}
		return $this->updateAll(
			$object,
			$this->soft_delete_column,
			$this->soft_delete_undeleted_value,
			'preSoftDeleteRestore',
			'postSoftDeleteRestore'
		);
	}

	/**
	 * Updates a field on all rows that match a model.
	 *
	 * @param object $object       Model or collection of models to update.
	 * @param string $field        The column name to update.
	 * @param mixed  $value        The value to set the column to.
	 * @param string $pre_trigger  Event to trigger callbacks for before
	 *                             updating, if any.
	 * @param string $post_trigger Event to trigger callbacks for after
	 *                             updating, if any.
	 *
	 * @return bool
	 */
	public function updateAll($object, $field, $value, $pre_trigger = null, $post_trigger = null) {

		$identifier = $this->getIdentifier();
		$collection = $this->getModelAsCollection($object);

		//
		// Trigger the pre-trigger, if there is one.
		if (isset($pre_trigger)) {
			foreach ($collection as $model) {
				if (!($this->triggerCallbacks($pre_trigger, $model))) {
					return false;
				}
			}
		}

		//
		// Get the property to update on each model, if there is one.
		$property_map = null;
		foreach ($this->map as $map) {
			if ($map['column'] === $field) {
				$property_map = $map;
				break;
			}
		}

		//
		// Get list of IDs to update, and update property on each model
		$id_values = array();
		foreach ($collection as $model) {
			if (isset($property_map)) {
				$this->setModelProperty($model, $property_map['property'], $value, $property_map);
			}
			$id_values[] = $this->getModelProperty($model, $identifier['property'], $identifier);
		}
		if (empty($id_values)) {
			return false;
		}

		//
		// Run update query
		$query = "
			UPDATE `{$this->getTable()}`
			SET `$field` = :value
			WHERE `{$identifier['column']}` IN {$this->getArrayPlaceholders($id_values, 'id')}
		";
		$statement = $this->database->prepare($query);
		$statement->bindValue('value', $value);
		foreach ($this->getArrayParameters($id_values, 'id') as $key => $id) {
			$statement->bindValue($key, $id);
		}
		$this->database->beginTransaction();
		$result = $statement->execute();
		$this->database->commit();

		//
		// Trigger the post-trigger, if there is one.
		if (isset($post_trigger)) {
			foreach ($collection as $model) {
				$this->triggerCallbacks($post_trigger, $model);
			}
		}

		return $result;
	}
}