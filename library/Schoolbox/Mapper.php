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
	    $this->database = \Database::getInstance();
	    $this->setup();
	    $this->mapping();
	    $this->setConfigLoader(new ConfigLoader());
	    $this->setColumnFactory(new ColumnFactory());
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

	/**
	 * Default setup for any other objects which compose the mapper.
	 */
    protected function setup() {}

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
     * Adds a mapping from a database column to a model property.
     *
     * @param array $map An array with any of the following keys:
     * * column: The database column to map from.
     * * property: The model property to map to. There must be a get and set
     *             method corresponding to the property in the model class:
     *             'get'.ucfirst($property)
     * * type:     The type of field this is. This affects the strategy that is
     *             used for hydrating database row fields into model attributes,
     *             and dehydrating model attributes to database fields. Use one
     *             of the following class constants:
     *             TYPE_BOOLEAN: true/false
     *             TYPE_INTEGER: an int
     *             TYPE_STRING, TYPE_LOB: no conversion is done
     *             TYPE_DATETIME, TYPE_DATE, TYPE_TIME, TYPE_TIMESTAMP:
     *               For all of these, the model is hydrated with an
     *               Alaress_Date_Format object. the type refers to how it's
     *               stored in the DB.
     *               DATETIME, DATE and TIME refer to their MySQL counterparts,
     *             TIMESTAMP refers to a Unix timestamp.
     *             TYPE_DECIMAL: currently, a number to 2 decimal places.
     *             TYPE_SERIALIZED: A PHP serialized value
     *             TYPE_MODEL: Another model.
     * * hydrator: An alternative to `type` - set this as an instance of Schoolbox\Column\IColumnType.
     *             Usually you won't need to set this directly, but if you have
     *             a hydration strategy that isn't covered by `type`, use this.
     * * join:     Required and only used for TYPE_MODEL - defines which join
     *             mapper should be used to hydrate an instance of this model for.
     * * identifier: if true, this map is the primary key for this table, and is
     *               used by mapper's getById method
     * created_timestamp: if true, this column is updated with the current time
     *                    when the row is created for the first time
     * updated_timestamp: like `created_timestamp`, but also updated when row is
     *                    updated at any time
     *
     * @return bool Was the map added?
     */
    protected function hasMap($map) {
        if (is_array($map) && !isset($map['hydrator'])) {
            $map['hydrator'] = $this->column_factory->build($map['type']);
        }

        if (!is_array($map)) {
            return false;
        }
        if (!empty($map['identifier'])) {
            $this->identifier = $map;
        }
        $this->map[] = $map;
        return true;
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
	 * set up, or hardDelete() method if it wasn't.
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
	 * Truly deletes an item.
	 *
	 * @param object $object Model or collection of models to delete
	 *
	 * @return bool
	 */
	public function hardDelete($object) {
		$identifierName = $this->identifier['column'];

		$collection = $this->getModelAsCollection($object);

		$statement = $this->database->prepare(
			"
            DELETE FROM  `{$this->table}`
            WHERE `{$identifierName}` = :identifierValue
        "
		);

		foreach ($collection as $model) {
			if (!($this->triggerCallbacks('preDelete', $model))) {
				return false;
			}
		}

		$this->database->beginTransaction();

		foreach ($collection as $model) {
			$statement->bindParam(':identifierValue', $this->getModelProperty($model, $identifierName, $this->identifier));

			if (!$statement->execute()) {

				return false;
			}
		}
		$this->database->commit();

		foreach ($collection as $model) {
			$this->triggerCallbacks('postDelete', $model);
		}

		return true;
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