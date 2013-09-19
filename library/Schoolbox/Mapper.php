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
use \Database;
use Schoolbox\Column\IColumnType;
use Schoolbox\Column\IFactory;
use Schoolbox\Mapper\Exception;

class Mapper {
	/**
	 * Represents a boolean data type.
	 */
	const TYPE_BOOLEAN = 5;

	/**
	 * Represents the SQL NULL data type.
	 */
	const TYPE_NULL = 0;

	/**
	 * Represents the SQL INTEGER data type.
	 */
	const TYPE_INTEGER = 1;

	/**
	 * Represents the SQL CHAR, VARCHAR, or other string data type.
	 */
	const TYPE_STRING = 2;

	/**
	 * Represents the SQL large object data type.
	 */
	const TYPE_LOB = 3;

	const TYPE_DATETIME   = 10;
	const TYPE_DATE       = 11;
	const TYPE_TIME       = 12;
	const TYPE_TIMESTAMP  = 13;
	const TYPE_DECIMAL    = 20;
	const TYPE_SERIALIZED = 30;
	const TYPE_MODEL      = 40;

	/**
	 * @var Database
	 */
	protected $database;

	/**
	 * @var string
	 */
	protected $table;

	/**
	 * Class name of the model to associate with this mapper.
	 *
	 * @var string
	 */
	protected $model;

	/**
	 * @var MappingCollection
	 */
	protected $map;

	/**
	 * A list of joins that this mapper has had defined.
	 *
	 * @var array
	 */
	protected $joins;

	/**
	 * @var array
	 */
	protected $identifier;

	/**
	 * Callbacks that are called on certain events within this mapper.
	 *
	 * @var array
	 */
	protected $callback_queue = array();

	/**
	 * Stores the singleton instance of the mapper
	 *
	 * @var array[Mapper]
	 */
	static protected $instance;

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
	 * Retreives the singleton instance of this class.
	 *
	 * @return static
	 */
	static public function getInstance() {
		$class_name = get_called_class();
		if (!isset(self::$instance[$class_name])) {
			self::$instance[$class_name] = new static();
		}
		return self::$instance[$class_name];
	}

	/**
	 * Overrides constructor as public, so that new instances can be created
	 * without going through getInstance().
	 */
	public function __construct() {
		$this->database = \Database::getInstance();
		$this->map = new MappingCollection();
		$this->setup();
		$this->setConfigLoader(new ConfigLoader());
		$this->setColumnFactory(new ColumnFactory());
		$this->mapping();
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
	 * @param  \Database $database
	 *
	 * @return $this
	 */
	public function setDatabase($database) {
		$this->database = $database;
		return $this;
	}

	/**
	 * @return \Database
	 */
	public function getDatabase() {
		return $this->database;
	}

	/**
	 * Gets a single object as a collection, if it isn't one already.
	 *
	 * Different possible actions based on $object:
	 * * instanceof Collection: returns that object
	 * * instanceof \ICollection: returns that object converted to collection
	 * * array: returns a Collection object wrapping that array
	 * * anything else: returns Collection with $object as only item
	 *
	 * @param mixed $object
	 *
	 * @return Collection
	 */
	public function getModelAsCollection($object) {
		if ($object instanceof Collection) {
			return $object;
		}
		if ($object instanceof \ICollection) {
			return $this->createCollection($object->getAll());
		}
		if (is_array($object)) {
			return $this->createCollection($object);
		}
		return $this->createCollection(array($object));
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
	 * Gets name of set method for identifier column.
	 *
	 * @return string
	 */
	public function getSetIdentifierMethodName() {
		$identifier = $this->getIdentifier();
		return $this->getSetMethodName($identifier['property']);
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
		$this->map->add($map);
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

	/**
	 * @return string
	 */
	public function getTable() {
		return $this->table;
	}

	/**
	 * @return string
	 */
	public function getModel() {
		return $this->model;
	}

	/**
	 * Retrieves identifier for this mapper.
	 *
	 * @return array
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * TODO: PDO Array support for using WHERE IN
	 *
	 * http://stackoverflow.com/questions/14767530/php-using-pdo-with-in-clause-array
	 * http://stackoverflow.com/questions/920353/php-pdo-can-i-bind-an-array-to-an-in-condition
	 *
	 * @param string $column
	 * @param array  $permittedValues
	 * @param bool   $deleted         If set to false, filters out deleted items.
	 * @return Collection
	 */
	public function findIn($column, $permittedValues, $deleted = false) {
		$query = "
			SELECT {$this->getColumnString()}
			FROM `{$this->table}`
			WHERE `{$column}` IN {$this->getArrayPlaceholders($permittedValues, 'val')}
		";
		if ($deleted && ($delete_part = $this->getSoftDeleteFilterSql())) {
			$query .= ' AND '.$delete_part;
		}
		$statement = $this->database->prepare($query);

		$statement->execute($this->getArrayParameters($permittedValues, 'val'));

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * @brief Retrieves the entire collection unfiltered
	 * @param bool $deleted If set to false, filters out deleted items.
	 * @return Collection
	 */
	public function findAll($deleted = false) {
		return $this->findByColumnList(array(), $deleted);
	}

	/**
	 * Retreives a model by id
	 *
	 * @throws Exception if no instance of model is found
	 * @throws Exception\Deleted if the model was found but has been deleted
	 * @param  mixed  $identifierValue
	 * @return object
	 */
	public function findById($identifierValue) {
		$identifierName = $this->identifier['column'];

		$statement = $this->database->prepare(
			"
			SELECT {$this->getColumnString()}
			FROM `{$this->table}`
			WHERE `{$identifierName}` = :identifierValue
		"
		);

		$statement->bindParam(':identifierValue', $identifierValue);

		$statement->execute();

		$row = $statement->fetch();
		if ($row != false) {
			$model = $this->hydrate($row);
			$this->throwIfDeleted($model);
			return $model;
		}
		throw new Exception('Instance of object not found');
	}

	/**
	 * Returns a collection with models filtered by column/property
	 *
	 * @param String $columnName
	 * @param String $columnValue
	 * @param bool   $deleted     If set to false, filters out deleted items.
	 *
	 * @return Collection
	 */
	public function findByColumn($columnName, $columnValue, $deleted = false) {
		return $this->findByColumnList(array($columnName => $columnValue), $deleted);
	}

	/**
	 * Returns first model whose $columnName = $columnValue.
	 *
	 * @throws Exception if no instance of model is found
	 * @throws Exception\Deleted if the model was found but has been deleted
	 * @param  String $columnName
	 * @param  String $columnValue
	 * @return object
	 */
	public function findOneByColumn($columnName, $columnValue) {
		$statement = $this->database->prepare("
			SELECT {$this->getColumnString()}
			FROM `{$this->table}`
			WHERE `{$columnName}` = :$columnName
			LIMIT 1
		");
		$statement->bindParam(":$columnName", $columnValue);
		$statement->execute();
		$result = $statement->fetch();
		if ($result) {
			$model = $this->hydrate($result);
			$this->throwIfDeleted($model);
			return $model;
		}
		throw new Exception('Instance of object not found');
	}

	/**
	 * @brief Returns a collection with models which match the values pairs in the column list
	 * @param Array $columnList
	 * @param bool  $deleted    If set to false, filters out deleted items.
	 * @return Collection
	 */
	public function findByColumnList($columnList, $deleted = false) {
		$query = "
			SELECT {$this->getColumnString()}
			FROM `{$this->table}`
		";

		$where_parts = array();
		$params      = array();
		if (!empty($columnList)) {
			$query .= ' WHERE ';
			foreach ($columnList as $columnName => $columnValue) {
				if (!isset($columnValue)) {
					$where_parts[] = "`$columnName` IS NULL";
				}
				else {
					$where_parts[] = "`$columnName` = ?";
					$params[]      = $columnValue;
				}
			}
			$query .= implode(' AND ', $where_parts);
		}
		if (!$deleted && ($delete_part = $this->getSoftDeleteFilterSql())) {
			$query .= (empty($columnList) ? ' WHERE ' : ' AND ').$delete_part;
		}

		$statement = $this->database->prepare($query);

		$statement->execute($params);

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

	/**
	 * @brief calls save(), but forces create by nulling the identifier field.
	 * @param $object
	 * @return array<int>
	 */
	public function create($object) {
		$collection = $this->getModelAsCollection($object);
		foreach ($collection as $model) {
			$this->setModelProperty($model, $this->identifier['property'], null, $this->identifier);
		}
		return $this->save($collection);
	}


	/**
	 * @brief Saves an object to the database. Updates or inserts
	 * @param $object
	 * @return array<int>
	 */
	public function save($object) {
		$collection = $this->getModelAsCollection($object);

		$tbl = $this->table;
		$colString = $this->getColumnString();
		$plcString = $this->getPlaceholderString();
		$updString = $this->getUpdateString();

		$statement = $this->database->prepare("
			INSERT INTO `$tbl` ($colString)
			VALUES ($plcString)
			ON DUPLICATE KEY UPDATE $updString;
		");
		$affectedIDs = array();

		//
		// Trigger all on collection before attempting to run?
		// Must for now, because otherwise transactions would be nested.
		foreach ($collection as $model) {
			if (!($this->triggerCallbacks('preSave', $model))) {
				return false;
			}
		}

		$identifier  = $this->getIdentifier();
		$id_property = $identifier['property'];
		$this->database->beginTransaction();
		foreach ($collection as $model) {
			$this->bindParamsForSave($model, $statement);
			if ($statement->execute()) {
				if ($id_property) {
					if (!$this->getModelProperty($model, $id_property, $identifier)) {
						$this->setModelProperty($model, $id_property, $this->database->lastInsertId(), $identifier);
					}
					$affectedIDs[] = $this->getModelProperty($model, $id_property, $identifier);
				}
			}
			else {
				$this->database->rollBack();
				return false;
			}
		}
		$this->database->commit();

		foreach ($collection as $model) {
			if ($id_property && $this->getModelProperty($model, $id_property, $identifier)) {
				$this->triggerCallbacks('postSave', $model);
			}
		}

		return $affectedIDs;
	}

	/**
	 * @brief Attempts to complete a partially defined map
	 *
	 * @deprecated Define all mappings yourself instead of relying on magic
	 *
	 * @return Bool
	 */
	protected function mapping() {
		if (empty($this->model)) {
			$modelName = substr(get_class($this), 0, -6);

			if (class_exists($modelName, true)) {
				$this->model = $modelName;
			} else {
				return false;
			}

		}

		if (empty($this->table)) {
			$this->table = $this->toUnderscore($this->model);
		}

		if ($this->map->count() === 0) {
			$propertyList = array_keys(get_class_vars($this->model));

			if (!empty($propertyList)) {

				foreach ($propertyList as $propertyName) {

					$this->hasMap(
						array(
							'column' => $this->toUnderscore($propertyName),
							'property' => $propertyName,
							'identifier' => $propertyName == 'id' ? true : false
						)
					);

				}

			} else {
				return false;
			}
		}

		return true;
	}

	protected function hasTable($table) {
		if (!is_string($table)) {
			return false;
		}

		$this->table = $table;

		return true;
	}

	protected function hasModel($model) {
		if (!is_string($model) || !class_exists($model, true)) {
			return false;
		}

		$this->model = $model;

		return true;
	}

	/**
	 * Adds a join to this mapper.
	 *
	 * @param string $name The identifier for this join.
	 * @param array  $join A definition for this join, with these keys:
	 *                     mapper:    a Mapper instance for the related table,
	 *                                or the class name of a mapper
	 *                     this_key:  the key for this side of the relation
	 *                     other_key: the key for the other side of the relation
	 *                     extra:     any extra conditions on the join
	 *
	 * @return bool
	 */
	protected function hasJoin($name, $join) {
		if (!is_array($join)) {
			return false;
		}
		$this->joins[$name] = $join;
		return true;
	}

	/**
	 * Retrieves the mapper that this join uses.
	 *
	 * @param string $name The identifier for this join.
	 *
	 * @return Mapper
	 */
	public function getJoinMapper($name) {
		if (!array_key_exists($name, $this->joins)) {
			return false;
		}
		$mapper = $this->joins[$name]['mapper'];
		if (!($mapper instanceof Mapper) && class_exists($mapper)) {
			$this->joins[$name]['mapper'] = call_user_func(array($mapper, 'getInstance'));
		}
		return $this->joins[$name]['mapper'];
	}

	/**
	 * Retrieves SQL for building a join that has been defined in hasJoin().
	 *
	 * @param string $name        The join's identifier as passed in hasJoin().
	 * @param string $this_alias  The alias to use for this side of the join,
	 *                            defaults to this mapper's table name.
	 * @param string $other_alias The alias to use for the other side of the
	 *                            join, defaults to other mapper's table name.
	 *
	 * @return string
	 */
	public function getJoinSql($name, $this_alias = '', $other_alias = '') {
		if (!array_key_exists($name, $this->joins)) {
			return false;
		}
		$join = $this->joins[$name];

		$this_table   = $this->getTable();
		$other_mapper = $this->getJoinMapper($name);
		$other_table  = $other_mapper->getTable();

		if (strlen($this_alias) === 0) {
			$this_alias = $this_table;
		}
		if (strlen($other_alias) === 0) {
			$other_alias = $other_table;
		}
		$join_sql = '`:other_table` AS `:other_alias` ON `:this_alias`.`:this_key` = `:other_alias`.`:other_key`';
		if (strlen($join['extra']) > 0) {
			$join_sql .= ' AND ' . $join['extra'];
		}
		return strtr(
			$join_sql,
			array(
				':this_alias'  => $this_alias,
				':this_key'    => $join['this_key'],
				':other_table' => $other_table,
				':other_alias' => $other_alias,
				':other_key'   => $join['other_key'],
			)
		);
	}

	/**
	 * Hydrates a row from the database into a model.
	 *
	 * @param array    $rowList  The rows from the database.
	 * @param string   $alias    The prefix of this mapper's table cells in $row
	 * @param callable $callback A callback to apply on all models (@see $this->hydrate)
	 *
	 * @return Collection
	 */
	public function hydrateList($rowList, $alias = '', $callback = null) {
		$modelList = $this->createCollection();

		foreach ($rowList as $row) {
			$modelList->add($this->hydrate($row, $alias, $callback));
		}

		return $modelList;
	}

	/**
	 * Hydrates a row from the database into a model.
	 *
	 * @param array    $row      The row from the database.
	 * @param string   $alias    The prefix of this mapper's table cells in $row
	 * @param callable $callback If supplied and callable, this method is
	 *                           applied to the model once it's retrieved.
	 *                           It takes the following parameters:
	 *                           $mapper: this object
	 *                           $model:  the model that has been hydrated
	 *                           $row:    the row from the database
	 *
	 * @return object
	 */
	public function hydrate($row, $alias = '', $callback = null) {
		$model = $this->createModel();

		foreach ($this->map as $map) {
			$propertyName = $map['property'];
			$columnName = $map['column'];

			if (!empty($alias)) {
				$columnName = "$alias.$columnName";
			}

			$columnValue = array_key_exists($columnName, $row) ? $row[$columnName] : null;
			$this->setModelProperty($model, $propertyName, $columnValue, $map);
		}

		if (!empty($callback) && is_callable($callback)) {
			$model = call_user_func($callback, $this, $model, $row);
		}

		return $model;
	}

	/**
	 * Helper method used by methods for finding a single model - this throws an
	 * exception if the model is deleted (it is an ISoftDeletable and isDeleted()
	 * returns true)
	 *
	 * @throws Exception\Deleted if the model is marked as deleted.
	 * @param  object $model
	 */
	protected function throwIfDeleted($model) {
		if ($model instanceof ISoftDeletable && $model->isDeleted()) {
			$e = new Exception\Deleted();
			$e->setDeletedObject($model);
			throw $e;
		}
	}

	/**
	 * Retrieves an SQL fragment for filtering out soft-deleted items.
	 *
	 * @param  string $alias The alias used for this table, if any.
	 * @return string
	 */
	protected function getSoftDeleteFilterSql($alias = null) {
		if (isset($this->soft_delete_column)) {
			$value = $this->soft_delete_undeleted_value;
			$column = "`{$this->soft_delete_column}`";
			if (isset($alias)) {
				$column = "`$alias`.$column";
			}
			if (!isset($value)) {
				return "$column IS NULL";
			}
			if ($value === false) {
				return "$column IS FALSE";
			}
			if ($value === true) {
				return "$column IS TRUE";
			}
			return "$column = ".$this->database->quote($value);
		}
		return null;
	}

	/**
	 * Creates a new instance of class with name passed in $this->hasModel().
	 *
	 * @return object
	 */
	public function createModel() {
		return new $this->model;
	}

	/**
	 * @brief Returns an SQL fragement containing a comma separated list of column names possibly aliased
	 * @param String $alias
	 * @param array  $whitelist If not empty, a whitelist of column names to use
	 * @return String
	 */
	public function getColumnString($alias = '', $whitelist = array()) {

		$columnList = array();

		foreach ($this->map as $map) {
			if (!empty($whitelist) && !in_array($map['column'], $whitelist)) {
				continue;
			}
			if (!empty($alias)) {
				$columnString = "`$alias`.`{$map['column']}` AS `$alias.{$map['column']}`";
			} else {
				$columnString = "`$this->table`.`{$map['column']}`";
			}

			$columnList[] = $columnString;
		}

		return implode(', ', $columnList);
	}

	/**
	 * @brief Returns a comma separated list of PDO placeholders. Works with Mapper::getColumnString
	 * @return String
	 */
	public function getPlaceholderString() {
		$placeholderList = array();

		foreach ($this->map as $map) {
			//
			// Is this a create or update timestamp field?
			// Update with now() instead of a placeholder.
			$placeholder = ":{$map['column']}";
			if (isset($map['created_timestamp']) || isset($map['updated_timestamp'])) {
				$placeholder = 'NOW()';
			}
			$placeholderList[] = $placeholder;
		}

		return implode(', ', $placeholderList);
	}

	/**
	 * Gets a string of placeholders for an array - usually used for creating a
	 * list of placeholders for an IN condition.
	 *
	 * @param array  $input
	 * @param string $prefix If not supplied, ? will be used as the placeholder
	 *                       for all values.
	 *
	 * @return string Something like "(:id_0, :id_1, :id_2)".
	 */
	public function getArrayPlaceholders($input, $prefix = null) {
		$placeholders = array();
		foreach ($input as $i => $param) {
			$placeholders[] = isset($prefix) ? (':'.$prefix.'_'.$i) : '?';
		}
		return '('.implode(',', $placeholders).')';
	}

	/**
	 * Flattens a list of parameters for an array.
	 *
	 * Used in conjunction with getArrayPlaceholders() - calling this with the
	 * same input and prefix will generate params and placeholders that work
	 * with each other.
	 *
	 * @param array  $input
	 * @param string $prefix
	 *
	 * @return array
	 */
	public function getArrayParameters($input, $prefix) {
		$params = array();
		foreach ($input as $i => $param) {
			$params[$prefix.'_'.$i] = $param;
		}
		return $params;
	}

	/**
	 * @brief Returns an SQL fragment containg the assignment portion of an UPDATE query
	 * @return String
	 */
	public function getUpdateString() {
		$assignmentList = array();

		foreach ($this->map as $map) {
			//
			// Don't update the identifier column, or the created timestamp.
			if (!empty($map['identifier'])) {
				continue;
			}
			if (isset($map['created_timestamp'])) {
				continue;
			}
			$assignment  = "`{$this->table}`.`{$map['column']}`";
			$placeholder = ":{$map['column']}";
			if (isset($map['updated_timestamp'])) {
				$placeholder = 'NOW()';
			}
			$assignmentList[] = "$assignment = $placeholder";
		}

		return implode(', ', $assignmentList);
	}

	/**
	 * Binds values from the model into the statement.
	 *
	 * @param object        $model     The model to get values from
	 * @param \PDOStatement $statement The statement for inserting/updating model
	 *
	 * @return void
	 */
	protected function bindParamsForSave($model, \PDOStatement $statement) {
		foreach ($this->map as $map) {
			$propertyName = $map['property'];
			$columnName   = $map['column'];
			//
			// Timestamp fields will never be bound.
			if (!(isset($map['updated_timestamp']) || isset($map['created_timestamp']))) {
				$statement->bindParam(":$columnName", $this->getModelProperty($model, $propertyName, $map));
			}
		}
	}

	/**
	 * @brief Converts an underscore separated string to camel case
	 * @param String $string
	 * @return String
	 */
	protected function toCamelcase($string) {
		$segmentList = explode('_', $string);

		foreach ($segmentList as &$segment) {
			$segment = ucfirst($segment);
		}

		return implode($segmentList);
	}

	/**
	 * @brief Explodes a camel case string apart at the humps using underscores
	 * @param String $string
	 * @return String
	 */
	public function toUnderscore($string) {
		$string = str_replace('_', '', $string);

		$segmentList = preg_split('/(?=[A-Z])/', $string, -1, PREG_SPLIT_NO_EMPTY);

		return strtolower(implode('_', $segmentList));
	}

	/**
	 * Registers a callback on a point.
	 *
	 * Available points: preSave, postSave, preDelete, postDelete
	 *
	 * @param string   $point
	 * @param callable $method
	 *
	 * @return $this
	 */
	public function registerCallback($point, $method) {
		$this->callback_queue[$point][] = $method;
		return $this;
	}

	public function triggerCallbacks($point) {
		$params = array_slice(func_get_args(), 1);
		if (array_key_exists($point, $this->callback_queue)) {
			foreach ($this->callback_queue[$point] as $method) {
				if (call_user_func_array($method, $params) === false) {
					//
					// Consider providing details on which callback failed.
					return false;
				}
			}
		}
		return true;
	}

}