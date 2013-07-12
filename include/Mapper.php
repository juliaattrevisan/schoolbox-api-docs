<?php

/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */

abstract class Mapper {
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
	 * @var array
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
     * @var Mapper
     */
    static protected $instance;

    /**
     * Retreives the singleton instance
     *
     * @static
     * @return static
     */
    static public function getInstance() {
        if (!isset($instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    protected function __construct() {
        $this->database = Database::getInstance();
        $this->setup();
        $this->mapping();
    }

	/**
	 * Default setup for any other objects which compose the mapper.
	 */
	protected function setup() {}

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
	 * @param $column
	 * @param $permittedValues
	 * @return Collection
	 */
	public function findIn($column, $permittedValues) {
		$statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `{$column}` IN {$this->getArrayPlaceholders($permittedValues, 'val')}
        ");

		$statement->execute($this->getArrayParameters($permittedValues, 'val'));

		$rowset = $statement->fetchAll();

		return $this->hydrateList($rowset);
	}

    /**
     * @brief Retrieves the entire collection unfiltered
     * @return Collection
     */
    public function findAll() {
        $statement = $this->database->prepare(
            "
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
        "
        );

        $statement->execute();

        $rowset = $statement->fetchAll();

        return $this->hydrateList($rowset);
    }

    /**
     * Retreives a model by id
     *
     * @param Mixed $identifierValue
     * @return Model
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
            return $model;
        } else {
            return false;
        }
    }

    /**
     * Returns a collection with models filtered by column/property
     *
     * @param String $columnName
     * @param String $columnValue
     *
     * @return Collection
     */
    public function findByColumn($columnName, $columnValue) {
        $statement = $this->database->prepare("
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
            WHERE `{$columnName}` = :$columnName
        ");
        $statement->bindParam(":$columnName", $columnValue);
        $statement->execute();
        return $this->hydrateList($statement->fetchAll());
    }

    /**
     * Returns first model whose $columnName = $columnValue.
     *
     * @param String $columnName
     * @param String $columnValue
     *
     * @return object or false if not found.
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
            return $this->hydrate($result);
        }
        return false;
    }

    /**
     * @brief Returns a collection with models which match the values pairs in the column list
     * @param Array $columnList
     * @return Bool|Collection
     */
    public function findByColumnList($columnList) {
        if (!is_array($columnList)) {
            return false;
        }

        $query = "
            SELECT {$this->getColumnString()}
            FROM `{$this->table}`
        ";

        $where_parts = array();
        $params      = array();
        if (!empty($columnList)) {
            $query .= ' WHERE ';
            foreach ($columnList as $columnName => $columnValue) {
                $where_parts[] = "`$columnName` = ?";
                $params[]      = $columnValue;
            }
            $query .= implode(' AND ', $where_parts);
        }

        $statement = $this->database->prepare($query);

        $statement->execute($params);

        $rowset = $statement->fetchAll();

        return $this->hydrateList($rowset);
    }


    /**
     * Gets a single object as a collection, if it isn't one already.
     *
     * Different possible actions based on $object:
     * * instanceof Model: returns Collection with $object as only item
     * * instanceof ICollection: returns that Collection
     * * array: returns a Collection object wrapping that array
     * * anything else: returns Collection with $object as only item
     *
     * @param mixed $object
     *
     * @return Collection
     */
	public function getModelAsCollection($object) {
		if ($object instanceof Model) {
			return $this->createCollection(array($object));
		}
		if ($object instanceof ICollection) {
			return $object;
		}
		if (is_array($object)) {
			return $this->createCollection($object);
		}
		else {
			return $this->createCollection(array($object));
		}
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

    public function delete($object) {
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
     * @brief Attempts to complete a partially defined map
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

        if (empty($this->map)) {
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

    protected function hasMap($map) {
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
     * @param Array $rowList
     * @param String $alias
     * @param Callback $callback
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
     * @param Array $row
     * @param String $alias
     * @param callable $callback
     * @return Model
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
     * Creates a new instance of class with name passed in $this->hasModel().
     *
     * @return object
     */
    public function createModel() {
        return new $this->model;
    }

    /**
     * Creates a new collection as returned by hydrateList().
     *
     * @param array $contents If set, init the collection with these elements.
     *
     * @return Collection
     */
    public function createCollection($contents = array()) {
        return new Collection($contents);
    }

    /**
     * Overridable method for getting a model's property.
     *
     * @param object $model
     * @param string $property
     * @param array  $map
     *
     * @return mixed
     */
    protected function getModelProperty($model, $property, $map) {
        return $model->$property;
    }

    /**
     * Overridable method for setting a model's property.
     *
     * @param object $model
     * @param string $property
     * @param mixed  $value
     * @param array  $map
     *
     * @return void
     */
    protected function setModelProperty($model, $property, $value, $map) {
        $model->$property = $value;
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
     * @param object       $model     The model to get values from
     * @param PDOStatement $statement The statement for inserting/updating model
     *
     * @return void
     */
    protected function bindParamsForSave($model, PDOStatement $statement) {
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
