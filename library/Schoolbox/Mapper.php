<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;

use Countable;
use Database;
use PDO;
use PDOStatement;
use Schoolbox\Column\Factory as ColumnFactory;
use Schoolbox\Column\IColumnType;
use Schoolbox\Column\IFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\NotifInterface;
use Schoolbox\Realtime\Schedule\Event;
use Serializable;
use Traversable;
use UnderflowException;

abstract class Mapper implements IMapper, Serializable
{
    /**
     * Represents a boolean data type.
     */
    const TYPE_BOOLEAN = 5;

    /**
     * Represents an almost-boolean data type: enum('true','false') in the database
     */
    const TYPE_BOOL_ENUM = 6;

    /**
     * Represents the SQL SET data type.
     */
    const TYPE_SET = 7;

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
    const TYPE_TIMESPAN   = 14;
    const TYPE_DECIMAL    = 20;
    const TYPE_SERIALIZED = 30;
    const TYPE_MODEL      = 40;
    const TYPE_COLLECTION = 41;
    const TYPE_COLOR      = 50;

    const METHOD_CREATE = 'create';
    const METHOD_READ   = 'read';
    const METHOD_UPDATE = 'update';
    const METHOD_DELETE = 'delete';

    // as per http://www.php.net/manual/en/pdostatement.rowcount.php#109891
    const PDO_RESULT_INSERT = 1;
    const PDO_RESULT_UPDATE = 2;

    // Valid points are preSave, postSave, preDelete, postDelete
    const CALLBACK_PRESAVE    = 'preSave';
    const CALLBACK_POSTSAVE   = 'postSave';
    const CALLBACK_PREDELETE  = 'preDelete';
    const CALLBACK_POSTDELETE = 'postDelete';
    const CALLBACK_PRESOFTDELETE  = 'preSoftDelete';
    const CALLBACK_POSTSOFTDELETE = 'postSoftDelete';
    const CALLBACK_PRERESTORE     = 'preSoftDeleteRestore';
    const CALLBACK_POSTRESTORE    = 'postSoftDeleteRestore';

    // options for mapping methods
    const COLUMN_IDENTIFIER        = 1;
    const COLUMN_CREATED_TIMESTAMP = 2;
    const COLUMN_UPDATED_TIMESTAMP = 4;
    const COLUMN_JOIN_MULTI        = 8;
    const COLUMN_JOIN_READONLY     = 16;
    const COLUMN_SOFTDELETE        = 32;

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
     * @deprecated use self::$prototype instead
     * @var string
     */
    protected $model;

    /**
     * @var object
     */
    protected $prototype;

    /**
     * Class name of the module notifier to associate with this mapper.
     *
     * @var string
     */
    protected $moduleNotifier;

    /**
     * @var MappingCollection
     */
    protected $map;

    /**
     * A list of joins that this mapper has had defined.
     *
     * @var array
     */
    protected $joins = array();

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
     * Notifier
     *
     * @var NotifInterface
     */
    protected $notif;

    /** @var array */
    protected $sessionData;

    /**
     * Retreives the singleton instance of this class.
     *
     * @return static
     */
    public static function getInstance()
    {
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
    public function __construct()
    {
        $this->database = \Database::getInstance();
        $this->map      = new MappingCollection();
        $this->setConfigLoader(new ConfigMapper());
        $this->setup();
        $this->setColumnFactory(new ColumnFactory());
        $this->setSessionData(isset($_SESSION) ? $_SESSION : array());
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
    protected function setup()
    {
    }

    /**
     * @param IFactory $column_factory
     *
     * @return $this
     */
    public function setColumnFactory(IFactory $column_factory)
    {
        $this->column_factory = $column_factory;
        return $this;
    }

    /**
     * @return IFactory
     */
    public function getColumnFactory()
    {
        return $this->column_factory;
    }

    /**
     * @param  \Schoolbox\IConfigLoader $config_loader
     *
     * @return $this
     */
    public function setConfigLoader($config_loader)
    {
        $this->config_loader = $config_loader;
        return $this;
    }

    /**
     * @return \Schoolbox\IConfigLoader
     */
    public function getConfigLoader()
    {
        return $this->config_loader;
    }

    /**
     * @param  \Database $database
     *
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @return \Database
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @return MappingCollection
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * @param NotifInterface $notif
     */
    public function setNotifier($notif)
    {
        $this->notif = $notif;
    }

    /**
     * @return NotifInterface
     */
    public function getNotifier()
    {
        // load on demand, so that we don't get infinite loops
        if (!$this->notif instanceof NotifInterface) {
            $this->setNotifier(new Notif(Notif::TOPIC_APPLICATION, $this->getConfigLoader()));
        }
        return $this->notif;
    }

    /**
     * @param array $sessionData
     */
    public function setSessionData($sessionData)
    {
        $this->sessionData = $sessionData;
    }

    /**
     * @return array
     */
    public function getSessionData()
    {
        return $this->sessionData;
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
    public function getModelAsCollection($object)
    {
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
     * Processes $collection into a hierarchical structure.
     *
     * @param  Collection $collection  The flat structure
     * @param  string     $parentField The name of the parent field. For most
     *                                 hierarchical mappers, this is "parent'
     * @param  string     $childField  The name of the children field.
     * @return Collection
     */
    public function getCollectionAsTree($collection, $parentField = 'parent', $childField = 'children')
    {
        $treeList = new Collection();

        // Process $collection into a tree list.
        // This can all be done in one pass, because objects are stored by
        // reference - setting children in $collection sets its children
        // in $treeList, regardless of where it is in the tree.
        $getId       = $this->getGetIdentifierMethodName();
        $getParent   = $this->getGetMethodName($parentField);
        $setChildren = $this->getSetMethodName($childField);
        foreach ($collection as $node) {
            $nodeId   = $node->$getId();
            $parentId = $node->$getParent()->$getId();
            if (!$parentId) {
                $treeList->add($node);
            }
            $node->$setChildren(
                $collection->filterByCallback(
                    function ($childNode) use ($nodeId, $getParent, $getId) {
                        return $childNode->$getParent()->$getId() == $nodeId;
                    }
                )
            );
        }
        unset($flatList);
        return $treeList;
    }

    /**
     * Loads all ancestors of this model or collection.
     *
     * Attempts to perform this action in as few queries as possible.
     *
     * @param        $object
     * @param string $parentField
     * @param int    $currentDepth
     *
     * @return $object
     */
    public function loadAncestors(
        $object,
        $parentField = 'parent',
        $currentDepth = 1
    ) {
        // Recursive relation protection
        if ($currentDepth <= 50) {
            return $object;
        }
        $collection = $this->getModelAsCollection($object);
        $getId      = $this->getGetIdentifierMethodName();
        $getParent  = $this->getGetMethodName($parentField);
        $setParent  = $this->getSetMethodName($parentField);

        // Get parent ID of each $object
        $parentIds = [];
        foreach ($collection as $node) {
            $parentId = $node->$getParent()->$getId();
            if ($parentId) {
                $parentIds[] = $parentId;
            }
        }

        if (!empty($parentIds)) {
            // Load all parents of $collection in one query
            $parents = [];
            foreach ($this->loadAncestors(
                $this->findByColumn($this->getIdentifier()['column'], $parentIds),
                $parentField,
                $currentDepth+1
            ) as $parent) {
                $parents[$parent->$getId()] = $parent;
            }
            // and add them as the parent of each item in $collection.
            foreach ($collection as $node) {
                $parentId = $node->$getParent()->$getId();
                if (array_key_exists($parentId, $parents)) {
                    $node->$setParent($parents[$parentId]);
                }
            }
        }
        return $object;
    }

    /**
     * Loads all descendants of this model or collection.
     *
     * Attempts to perform this action in as few queries as possible.
     *
     * @param        $object
     * @param string $parentField
     * @param string $childField
     * @param int    $currentDepth
     *
     * @return $object
     */
    public function loadDescendants(
        $object,
        $parentField = 'parent',
        $childField = 'children',
        $currentDepth = 1
    ) {
        // Recursive relation protection
        if ($currentDepth > 50) {
            return $object;
        }

        $collection  = $this->getModelAsCollection($object);
        $getId       = $this->getGetIdentifierMethodName();
        $getParent   = $this->getGetMethodName($parentField);
        $setChildren = $this->getSetMethodName($childField);

        // Get IDs of each $object, use them to get children
        $ids = [];
        foreach ($collection as $node) {
            $id = $node->$getId();
            if ($id) {
                $ids[] = $id;
            }
        }

        if (!empty($ids)) {
            // Load all children of $collection in one query
            $children = [];
            foreach ($this->loadDescendants(
                $this->findByColumn($this->getMap()->getOffset($parentField)['column'], $ids),
                $parentField,
                $childField,
                $currentDepth+1
            ) as $child) {
                $children[$child->$getParent()->$getId()][] = $child;
            }
            // and add them as the children of each item in $collection.
            foreach ($collection as $node) {
                $id = $node->$getId();
                if (array_key_exists($id, $children)) {
                    $node->$setChildren($this->createCollection($children[$id]));
                }
            }
        }
        return $object;
    }

    /**
     * Gets name of get method for identifier column.
     *
     * @return string
     */
    public function getGetIdentifierMethodName()
    {
        $identifier = $this->getIdentifier();
        return $this->getGetMethodName($identifier['property']);
    }

    /**
     * Gets name of set method for identifier column.
     *
     * @return string
     */
    public function getSetIdentifierMethodName()
    {
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
    public function getGetMethodName($property)
    {
        return 'get' . ucfirst($property);
    }

    /**
     * Gets name of set method for given property.
     *
     * @param string $property
     *
     * @return string
     */
    public function getSetMethodName($property)
    {
        return 'set' . ucfirst($property);
    }

    /**
     * Overridden to use getter and hydrator instead of getting property
     * directly.
     *
     * @param object $model
     * @param string $property
     *
     * @return mixed
     */
    protected function getModelProperty($model, $property)
    {
        $get_method  = $this->getGetMethodName($property);
        $value       = $model->$get_method();
        $map         = $this->map->getOffset($property);
        $join_mapper = isset($map['join']) ? $this->getJoinMapper($map['join']) : null;
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->dehydrate($value, $join_mapper);
        }
        return $value;
    }

    /**
     * Translates a column to a property field
     *
     * @param $column
     * @return mixed
     */
    protected function getPropertyFromColumn($column) {
        foreach($this->map as $map) {
            if($map['column'] == $column) {
                return $map['property'];
            }
        }
    }

    /**
     * Overridden to use setter and hydrator instead of setting property
     * directly.
     *
     * @param object $model
     * @param string $property
     * @param mixed  $value
     *
     * @return $this
     */
    protected function setModelProperty($model, $property, $value)
    {
        $set_method  = $this->getSetMethodName($property);
        $map         = $this->map->getOffset($property);
        $join_mapper = isset($map['join']) ? $this->getJoinMapper($map['join']) : null;
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->hydrate($value, $join_mapper);
        }
        $model->$set_method($value);
        return $this;
    }


    /**
     * @param string             $column
     * @param string|IColumnType $type
     * @param int                $options
     * @param string             $property
     * @return array
     */
    private function buildColumnMap($column, $type, $options, $property)
    {
        $map = [
            'column' => $column,
        ];
        if ($property) {
            $map['property'] = $property;
        }
        if ($type instanceof IColumnType) {
            $map['hydrator'] = $type;
        } else {
            $map['type'] = $type;
        }
        // boolean options
        if ($options & self::COLUMN_IDENTIFIER) {
            $map['identifier'] = true;
        }
        if ($options & self::COLUMN_CREATED_TIMESTAMP) {
            $map['created_timestamp'] = true;
        }
        if ($options & self::COLUMN_UPDATED_TIMESTAMP) {
            $map['updated_timestamp'] = true;
        }
        if ($options & self::COLUMN_SOFTDELETE) {
            if (in_array($type, [self::TYPE_TIMESTAMP, self::TYPE_DATETIME, self::TYPE_DATE])) {
                $map['softdelete_timestamp'] = true;
            } else {
                $map['softdelete_boolean'] = true;
            }
        }
        return $map;
    }


    /**
     * The concise, typesafe version of hasMap()
     *
     * @param string $column
     * @param string|IColumnType $type
     * @param int    $options
     * @param string $property
     * @return bool
     */
    protected function uses($column, $type, $options = null, $property = null)
    {
        return $this->hasMap($this->buildColumnMap($column, $type, $options, $property));
    }


    /**
     * The concise, typesafe version of hasMap() + hasJoin()
     *
     * @param string      $column
     * @param string      $foreignMapper
     * @param string      $foreignKey
     * @param IColumnType $hydrator
     * @param int         $options
     * @param string      $property
     * @param string      $name
     * @return bool
     */
    protected function joins($column, $foreignMapper, $foreignKey = 'id', $hydrator = null, $options = null, $property = null, $name = null)
    {
        if ($hydrator) {
            $type = $hydrator;
        } elseif ($options & self::COLUMN_JOIN_MULTI) {
            $type = self::TYPE_COLLECTION;
        } else {
            $type = self::TYPE_MODEL;
        }
        $map = $this->buildColumnMap($column, $type, $options, $property);
        $map['join'] = $name ?: $column;
        $map['join_def'] = [
            'mapper'    => $foreignMapper,
            'other_key' => $foreignKey
        ];
        return $this->hasMap($map);
    }


    /**
     * The concise, typesave version of hasMap() + hasJoin(['multi'])
     *
     * @param string          $viaTable      the manymany jointable
     * @param string          $foreignMapper the target Mapper on the other side (not a jointable Mapper)
     * @param string          $foreignKey    the ID field of the foreign Model
     * @param string          $viaForeign    the fieldname on the foreign side of the jointable
     * @param string          $viaLocal      the fieldname of the local side of the jointable
     * @param string          $localKey      optionally, the ID field of this Model, defaults to $this->identifier otherwise
     * @param int|IColumnType $hydrator      optionally, a custom hydrator
     * @param int             $options       optionally, any custom options required
     * @param string          $property      opyionally, override the property name from default
     * @throws Exception
     */
    protected function joinsVia($viaTable, $foreignMapper, $foreignKey, $viaForeign, $viaLocal, $localKey = null, $hydrator = null, $options = null, $property = null)
    {
        if (!$localKey && !$this->identifier) {
            throw new Exception("No identifier specified, either provide one or declare a primary key first");
        }
        $type = $hydrator ?: self::TYPE_COLLECTION;
        $map  = $this->buildColumnMap('', $type, $options, $property);
        // there is no local column in this instance
        unset($map['column']);
        $map['join'] = $viaTable;
        $map['join_def'] = [
            'mapper'    => $foreignMapper,
            'this_key'  => $localKey ?: $this->identifier['column'],
            'other_key' => $foreignKey,
            'multi'     => [
                'via_table'  => $viaTable,
                'this_key'   => $viaLocal,
                'other_key'  => $viaForeign,
                'read_only'  => !!($options & self::COLUMN_JOIN_READONLY)
            ]
        ];
        $this->hasMap($map);
    }


    /**
     * Adds a mapping from a database column to a model property.
     *
     * @param array $map  An array with any of the following keys:
     *                    * column: The database column to map from, ignored if a join specified is declared 'multi'.
     *                    * property: The model property to map to. There must be a get and set
     *                    method corresponding to the property in the model class:
     *                    'get'.ucfirst($property)
     *                    * type:     The type of field this is. This affects the strategy that is
     *                    used for hydrating database row fields into model attributes,
     *                    and dehydrating model attributes to database fields. Use one
     *                    of the following class constants:
     *                    TYPE_BOOLEAN: true/false
     *                    TYPE_INTEGER: an int
     *                    TYPE_STRING, TYPE_LOB: no conversion is done
     *                    TYPE_DATETIME, TYPE_DATE, TYPE_TIME, TYPE_TIMESTAMP:
     *                    For all of these, the model is hydrated with an
     *                    Alaress_Date_Format object. the type refers to how it's
     *                    stored in the DB.
     *                    DATETIME, DATE and TIME refer to their MySQL counterparts,
     *                    TIMESTAMP refers to a Unix timestamp.
     *                    TYPE_DECIMAL: currently, a number to 2 decimal places.
     *                    TYPE_SERIALIZED: A PHP serialized value
     *                    TYPE_MODEL: Another model.
     *                    TYPE_COLLECTION: A set of another model.
     *                    * hydrator: An alternative to `type` - set this as an instance of Schoolbox\Column\IColumnType.
     *                    Usually you won't need to set this directly, but if you have
     *                    a hydration strategy that isn't covered by `type`, use this.
     *                    * join:     Required and only used for TYPE_MODEL - defines which join
     *                    mapper should be used to hydrate an instance of this model for.
     *                    * join_def: if 'join' is supplied, this can be used instead of defining
     *                    join in hasJoin().
     *                    If 'this_key' is not set here, then it defaults to the value
     *                    for 'column'.
     *                    * identifier: if true, this map is the primary key for this table, and is
     *                    used by mapper's getById method
     *                    created_timestamp: if true, this column is updated with the current time
     *                    when the row is created for the first time
     *                    updated_timestamp: like `created_timestamp`, but also updated when row is
     *                    updated at any time
     *
     * @return bool Was the map added?
     */
    protected function hasMap($map)
    {
        if (!is_array($map)) {
            return false;
        }

        if (empty($map['hydrator'])) {
            $map['hydrator'] = $this->column_factory->build($map['type']);
        }

        // if property is empty, derive it from the column, converting snake_case to camelCase
        if (empty($map['property'])) {
            // if type is model, truncate '_id' suffix if it exists
            if ($map['type'] == self::TYPE_MODEL && substr($map['column'], -3) == '_id') {
                $property = substr($map['column'], 0, -3);
            } else {
                $property = $map['column'];
            }
            $map['property'] = score2camel($property);
        }

        if (isset($map['identifier'])) {
            $this->identifier = $map;
        }

        $this->map->setOffset($map['property'], $map);

        // Has a join definition been defined? If so create the join.
        if (isset($map['join']) && isset($map['join_def'])) {
            if (!isset($map['join_def']['this_key'])) {
                $map['join_def']['this_key'] = $map['column'];
            }
            $this->hasJoin($map['join'], $map['join_def']);
        }

        // has a softdelete definition been defined? if so create the softdelete

        if (isset($map['softdelete_timestamp'])) {
            $this->hasSoftDeleteDateField($map['column']);
        } elseif (isset($map['softdelete_boolean'])) {
            $this->hasSoftDeleteBooleanField($map['column']);
        }

        return true;
    }

    /**
     * Override base mapper to always return new collection object
     *
     * @param array $contents
     *
     * @return Collection
     */
    public function createCollection($contents = array())
    {
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
    protected function hasSoftDeleteBooleanField($column)
    {
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
    protected function hasSoftDeleteDateField($column)
    {
        return $this->hasSoftDeleteField($column, date('Y-m-d H:i:s'), null);
    }

    /**
     * Sets this mapper as implementing soft deletes.
     *
     * @param string $column          The name of the column used for storing whether a
     *                                row is soft deleted.
     * @param mixed  $deleted_value   The value to set the column to when a row
     *                                is deleted, defaults to true.
     * @param mixed  $undeleted_value The value to set the column to when a row
     *                                is not deleted, defaults to false.
     *
     * @return $this
     */
    protected function hasSoftDeleteField($column, $deleted_value = true, $undeleted_value = false)
    {
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
    public function delete($object)
    {
        if (isset($this->soft_delete_column)) {
            return $this->softDelete($object);
        } else {
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
    public function softDelete($object)
    {
        if (!isset($this->soft_delete_column)) {
            return false;
        }
        $collection = $this->getModelAsCollection($object);
        $success    = $this->updateAll(
            $collection,
            $this->soft_delete_column,
            $this->soft_delete_deleted_value,
            'preSoftDelete',
            'postSoftDelete'
        );
        if ($success) {
            // raise delete notification
            foreach ($collection as $model) {
                $this->raiseNotification($model, self::METHOD_DELETE);
            }
        }
        return $success;
    }

    /**
     * Truly deletes an item.
     *
     * @param object $object Model or collection of models to delete
     *
     * @return bool
     */
    public function hardDelete($object)
    {
        $identifierName     = $this->identifier['column'];
        $identifierProperty = $this->identifier['property'];
        $collection         = $this->getModelAsCollection($object);
        $statement          = $this->database->prepare(
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
            $statement->bindValue(':identifierValue', $this->getModelProperty($model, $identifierProperty));
            if (!$statement->execute()) {
                return false;
            }
        }
        $this->database->commit();
        foreach ($collection as $model) {
            $this->triggerCallbacks('postDelete', $model);
        }
        // raise delete notification
        foreach ($collection as $model) {
            $this->raiseNotification($model, self::METHOD_DELETE);
        }
        return true;
    }


    /**
     * Deletes (whether hard or soft) all records that match the given column value
     * Returns the number of records deleted.
     *
     * @param $columnName
     * @param $columnValue
     *
     * @return int
     */
    public function deleteByColumn($columnName, $columnValue)
    {
        return $this->deleteByColumnList(array($columnName => $columnValue));
    }


    /**
     * Deletes (whether hard or soft) all records that match the given set of column values
     * Returns the number of records deleted.
     *
     * @param array $columnList
     *
     * @return int
     */
    public function deleteByColumnList($columnList)
    {
        $quoted_table = $this->database->quoteIdentifier($this->table);
        // only soft-delete if this is a soft-deletable model
        if ($softDelete = $this->getSoftDeleteUpdateSql()) {
            $query = "UPDATE $quoted_table SET $softDelete WHERE ";
        } else {
            $query = "DELETE FROM $quoted_table WHERE ";
        }
        // Build WHERE part
        $where_parts = array();
        $params      = array();
        // as an OH&S measure, the column list MUST NOT be empty (and thus match all records)
        if (empty($columnList)) {
            return 0;
        }
        foreach ($columnList as $columnName => $columnValue) {
            $escaped_name = $this->database->quoteIdentifier($columnName);
            if (!isset($columnValue)) {
                $where_parts[] = "$escaped_name IS NULL";
            } else {
                // This method is smart enough to work with arrays and IN statements
                // If the list of values is empty, though: there's no way
                // that the query could ever return anything, so just return
                // an empty collection.
                if (is_array($columnValue)) {
                    if (count($columnValue) === 0) {
                        return 0;
                    }
                    $where_parts[] = "$escaped_name IN {$this->getArrayPlaceholders($columnValue, $columnName)}";
                    $params        = array_merge($params, $this->getArrayParameters($columnValue, $columnName));
                } else {
                    $where_parts[]              = "$escaped_name = :{$columnName}_0";
                    $params[$columnName . '_0'] = $columnValue;
                }
            }
        }
        $query .= implode(' AND ', $where_parts);
        $statement = $this->database->prepare($query);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();
        return $statement->rowCount();
    }


    /**
     * Restores one or more models that were soft deleted.
     *
     * @param object $object Model or collection of models to restore
     *
     * @return bool
     */
    public function restoreSoftDeleted($object)
    {
        if (!isset($this->soft_delete_column)) {
            return false;
        }
        return $this->updateAll(
            $object,
            $this->soft_delete_column,
            $this->soft_delete_undeleted_value,
            self::CALLBACK_PRERESTORE,
            self::CALLBACK_POSTRESTORE
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
    public function updateAll($object, $field, $value, $pre_trigger = null, $post_trigger = null)
    {
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
                $this->setModelProperty($model, $property_map['property'], $value);
            }
            $id_values[] = $this->getModelProperty($model, $identifier['property']);
        }
        if (empty($id_values)) {
            return false;
        }
        //
        // Run update query
        $query     = "
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
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return string
     */
    public function getModuleNotifier()
    {
        return $this->moduleNotifier;
    }

    /**
     * Retrieves identifier for this mapper.
     *
     * @return array
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * TODO: PDO Array support for using WHERE IN
     *
     * @deprecated findByColumn/findByColumnList now have array support
     *
     * http://stackoverflow.com/questions/14767530/php-using-pdo-with-in-clause-array
     * http://stackoverflow.com/questions/920353/php-pdo-can-i-bind-an-array-to-an-in-condition
     *
     * @param string $column
     * @param array  $permittedValues
     * @param array  $options @see findByColumnList() $options param
     *
     * @return Collection
     */
    public function findIn($column, $permittedValues, $options = array())
    {
        return $this->findByColumn($column, $permittedValues, $options);
    }

    /**
     * @brief Retrieves the entire collection unfiltered
     *
     * @param array $options @see findByColumnList() $options param
     *
     * @return Collection
     */
    public function findAll($options = array())
    {
        return $this->findByColumnList(array(), $options);
    }

    /**
     * Retreives a model by id
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  mixed $identifierValue
     *
     * @return object
     */
    public function findById($identifierValue)
    {
        return $this->findOneByColumn($this->identifier['column'], $identifierValue);
    }

    /**
     * Returns a collection with models filtered by column/property
     *
     * @param String $columnName
     * @param String $columnValue
     * @param array  $options @see findByColumnList() $options param
     *
     * @return Collection
     */
    public function findByColumn($columnName, $columnValue, $options = array())
    {
        return $this->findByColumnList(array($columnName => $columnValue), $options);
    }

    /**
     * Returns first model whose $columnName = $columnValue.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  String $columnName
     * @param  String $columnValue
     * @param  array  $order Fields to order by (@see findByColumnList() $options param)
     *
     * @return object
     */
    public function findOneByColumn($columnName, $columnValue, $order = array())
    {
        return $this->findOneByColumnList(array($columnName => $columnValue), $order);
    }

    /**
     * Returns first model who matches all the values pairs in $columnList.
     *
     * @throws Exception if no instance of model is found
     * @throws Exception\Deleted if the model was found but has been deleted
     *
     * @param  array $columnList
     * @param  array $order Fields to order by (@see findByColumnList() $options param)
     *
     * @return object
     */
    public function findOneByColumnList($columnList, $order = array())
    {
        $result_collection = $this->findByColumnList(
            $columnList,
            array(
                'deleted' => true,
                'order'   => $order,
            )
        );
        foreach ($result_collection as $model) {
            $this->throwIfDeleted($model);
            return $model;
        }
        throw new Exception('Instance of object not found');
    }

    /**
     * @brief Returns a collection with models which match the values pairs in the column list
     *
     * @param array $columnList                  a set of key-value pairs:
     *                                           * key:   the name of the column to find
     *                                           * value: the value (or array of values) to search for
     * @param array $options                     * limit, offset: limit and offset for query
     *                                           * deleted:       whether to retrieve deleted items
     *                                           (defaults to false)
     *                                           * order:         a set of key-value pairs:
     *                                           key: column name, value: ASC or DESC
     *
     * @throws Exception if for some reason the limit or offset parameters
     *                   cannot be created (this should never happen in practise
     *                   as columnList would have to contain a field named
     *                   'limit' or 'offset')
     * @return Collection
     */
    public function findByColumnList($columnList, $options = array())
    {
        // Compatibility with old deleted option: if a non-array was passed,
        // assume that we're asking for whether the item was deleted.
        if (!is_array($options)) {
            $options = array('deleted' => $options,);
        }
        $defaults     = array(
            'limit'   => null,
            'offset'  => null,
            'deleted' => false,
            'order'   => array(),
        );
        $options      = array_merge($defaults, $options);
        $quoted_table = $this->database->quoteIdentifier($this->table);
        $query        = $this->getSelectString();
        $params      = array();
        if (!empty($columnList)) {
            // Build WHERE part
            // If it underflows, someone passed an empty column value - return an
            // empty set.
            try {
                $where = $this->buildWherePart($columnList, $options);
            } catch (\UnderflowException $e) {
                return $this->hydrateList(array());
            }
            $where_parts = $where['where'];
            $params      = $where['params'];
            $query .= ' WHERE ';
            $query .= implode(' AND ', $where_parts);
        }
        if (!$options['deleted'] && ($delete_part = $this->getSoftDeleteFilterSql())) {
            $query .= (empty($columnList) ? ' WHERE ' : ' AND ') . $delete_part;
        }
        // Build ORDER part (in an injection-safe way)
        $order_parts = array();
        if (!empty($options['order'])) {
            foreach ($options['order'] as $column => $order_option) {
                $escaped_name = $this->database->quoteIdentifier($column);
                $order_option = strtoupper($order_option);
                if (in_array($order_option, array('ASC', 'DESC'))) {
                    $order_parts[] = "$quoted_table.$escaped_name $order_option";
                }
            }
        }
        if (!empty($order_parts)) {
            $query .= ' ORDER BY ' . (implode(', ', $order_parts));
        }
        // Build LIMIT and OFFSET part (should bind as parameters instead)
        $limit  = null;
        $offset = null;
        if (isset($options['limit'])) {
            $query .= " LIMIT :limit";
            $limit = $options['limit'];
            if (isset($options['offset'])) {
                $query .= " OFFSET :offset";
                $offset = $options['offset'];
            }
        } elseif (isset($options['offset'])) {
            // Offset but no limit? all we can do is use some massive number as
            // the limit.
            $query .= " LIMIT :limit OFFSET :offset";
            $limit  = PHP_INT_MAX;
            $offset = $options['offset'];
        }
        $statement = $this->database->prepare($query);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        // Bind limit and offset if they were set
        if (isset($limit)) {
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        }
        if (isset($offset)) {
            $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        }
        $statement->execute();

        return $this->hydrateSelect($statement);
    }


    /**
     * @return string
     */
    protected function getSelectString()
    {
        // in the core mapper this is quite trivial, but implementing classes can override
        $quoted_table = $this->database->quoteIdentifier($this->table);
        return "SELECT {$this->getColumnString()} FROM $quoted_table";
    }


    /**
     * @param PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // in the core mapper this is quite trivial, but implementing classes can override
        $rowset = $statement->fetchAll();
        return $this->hydrateList($rowset);
    }


    /**
     * @see $this->findAll()
     *
     * @param  array $options
     *
     * @return int
     */
    public function countAll($options = array())
    {
        return $this->countByColumnList(array(), $options);
    }

    /**
     * @see $this->findByColumn()
     *
     * @param string $columnName
     * @param string $columnValue
     * @param array  $options
     *
     * @return int
     */
    public function countByColumn($columnName, $columnValue, $options = array())
    {
        return $this->countByColumnList(array($columnName => $columnValue), $options);
    }

    /**
     * @see $this->findByColumnList()
     *
     * @param  array $columnList
     * @param  array $options
     *
     * @return int
     */
    public function countByColumnList($columnList, $options = array())
    {
        try {
            $stmt = $this->selectScalarByColumnList('COUNT(*)', $columnList, $options);
            return intval($stmt->fetchColumn());
        } catch (UnderflowException $e) {
            return 0;
        }
    }


    /**
     * Returns a statement that attempts to select the scalar given by $selection
     *
     * @param  string $selection
     * @param  array  $columnList
     * @param  array  $options
     *
     * @throws UnderflowException YOU MUST CATCH THIS. Thrown if the query could not possibly return any results.
     * @return PDOStatement
     */
    protected function selectScalarByColumnList($selection, array $columnList, array $options = [])
    {
        // set options (deleted is the only relevant one)
        if (!is_array($options)) {
            $options = array('deleted' => $options,);
        }
        $defaults    = array(
            'deleted' => false,
        );
        $options     = array_merge($defaults, $options);
        $quotedTable = $this->database->quoteIdentifier($this->table);
        $query       = "SELECT $selection FROM $quotedTable";
        $params      = array();
        // Build WHERE part
        // If it underflows, someone passed an empty column value - return an
        // empty set.
        if (!empty($columnList)) {
            $where = $this->buildWherePart($columnList, $options);
            $whereParts = $where['where'];
            $params     = $where['params'];
            $query .= ' WHERE ';
            $query .= implode(' AND ', $whereParts);
        }
        // Include soft delete filter
        if (!$options['deleted'] && ($deletePart = $this->getSoftDeleteFilterSql())) {
            $query .= (empty($columnList) ? ' WHERE ' : ' AND ') . $deletePart;
        }
        // Build query and bind parameters to it
        $statement = $this->database->prepare($query);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->execute();

        return $statement;
    }


    /**
     * @see $this->findAll()
     *
     * @param  array $options
     * @return array
     */
    public function findAllIds(array $options = [])
    {
        return $this->findIdsByColumnList([], $options);
    }


    /**
     * @see $this->findByColumn()
     *
     * @param string $columnName
     * @param mixed  $columnValue
     * @param array  $options
     * @return array
     */
    public function findIdsByColumn($columnName, $columnValue, array $options = [])
    {
        return $this->findIdsByColumnList([ $columnName => $columnValue ], $options);
    }


    /**
     * @param array $columnList
     * @param array $options
     * @return array
     */
    public function findIdsByColumnList(array $columnList, array $options = [])
    {
        try {
            $stmt = $this->selectScalarByColumnList($this->identifier['column'], $columnList, $options);
        } catch (UnderflowException $e) {
            return [];
        }
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }


    /**
     * Builds a where part and params for this query out of a column list
     *
     * @param  array $columnList
     * @param  array $options
     *
     * @return array where:  The where parts of the query
     *               params: the parameters
     * @throws \UnderflowException
     */
    protected function buildWherePart($columnList, $options)
    {
        $where_parts = array();
        $params      = array();
        foreach ($columnList as $columnName => $columnValue) {

            // accommodate joins
            $xpl = explode('.', $columnName) ?: [];

            if (count($xpl) == 1) {
                $table = $this->getTable();
                $field = $xpl[0];
            } elseif (count($xpl) == 2) {
                $table = $xpl[0];
                $field = $xpl[1];

                // remove the period so SQL doesn't freak out
                $columnName = str_replace('.', '_', $columnName);

            } else {
                // bad
                continue;
            }

            $escaped_name = $this->database->quoteIdentifier($table) . '.' . $this->database->quoteIdentifier($field);
            if (!isset($columnValue)) {
                $where_parts[] = "$escaped_name IS NULL";
            } else {
                // This method is smart enough to work with arrays and IN statements
                // If the list of values is empty, though: there's no way
                // that the query could ever return anything, so just return
                // an empty collection.
                if (is_array($columnValue)
                    || ($columnValue instanceof Traversable && $columnValue instanceof Countable)
                ) {
                    if (count($columnValue) === 0) {
                        throw new \UnderflowException('List of column values is empty');
                    }
                    $where_parts[] = "$escaped_name IN {$this->getArrayPlaceholders($columnValue, $columnName)}";
                    $params        = array_merge($params, $this->getArrayParameters($columnValue, $columnName));
                } else {
                    $where_parts[]              = "$escaped_name = :{$columnName}_0";
                    $params[$columnName . '_0'] = $columnValue;
                }
            }
        }
        return array(
            'where'  => $where_parts,
            'params' => $params,
        );
    }

    /**
     * Finds a collection of models that are joined to this one.
     *
     * @param string $join    The name of a join in this mapper (set up with
     *                        $this->hasJoin($join, ...))
     * @param object $object  A model, or collection thereof.
     * @param array  $options Optional options to pass to query
     *                        (@see findByColumnList() $options param)
     *
     * @return Collection
     */
    public function findJoinedModelList($join, $object, $options = array())
    {
        // Retrieve a list of column values for the join for $object
        // (the column: whatever the key is in this model.)
        $join_identifier = $this->getJoinDefinition($join);
        $mapping         = $this->map->getOffsetByColumn($join_identifier['this_key']);
        $key_value_list  = $this->getModelAsCollection($object)->getColumnValues($mapping['property']);
        // If the join is a many or one to one relation:
        // it is likely that each value is an object (because the mapping was defined as a model type).
        $join_mapper = $this->getJoinMapper($join);
        if ($mapping['type'] === self::TYPE_MODEL) {
            $get_id_method = $join_mapper->getGetIdentifierMethodName();
            foreach ($key_value_list as &$key) {
                $key = call_user_func(array($key, $get_id_method));
            }
            unset($key);
        }
        return $join_mapper->findByColumn($join_identifier['other_key'], $key_value_list, $options);
    }

    /**
     * Loads joined models into this object.
     *
     * @param string $join                @see $this->findJoinedModelList() $join param
     * @param object $object              @see $this->findJoinedModelList() $object param
     * @param array  $options             @see $this->findJoinedModelList() $options param
     *                                    Also takes another param:
     *                                    set_method: the setter method for setting the
     *                                    joined models in each $object
     */
    public function loadJoinedModel($join, $object, $options = array())
    {
        $collection        = $this->getModelAsCollection($object);
        $joined_model_list = $this->findJoinedModelList($join, $collection, $options);
        $join_mapper       = $this->getJoinMapper($join);
        $join_identifier   = $this->getJoinDefinition($join);
        $this_mapping      = $this->getMap()->getOffsetByColumn($join_identifier['this_key']);
        $other_mapping     = $join_mapper->getMap()->getOffsetByColumn($join_identifier['other_key']);
        // Hydrate joined models into $collection.
        // Need to know:
        // * how it relates back to this model ($join['other_key'])
        // * how to set it in this model ($join['this_key')
        $this_get_method   = $this->getGetMethodName($this_mapping['property']);
        $other_get_method  = $join_mapper->getGetMethodName($other_mapping['property']);
        $is_this_model     = $this_mapping['type'] === self::TYPE_MODEL;
        $is_other_model    = $other_mapping['type'] === self::TYPE_MODEL;
        $this_id_method    = $this->getGetIdentifierMethodName();
        $grouped_join_list = $joined_model_list->groupByCallback(
            function ($item) use ($other_get_method, $is_other_model, $this_id_method) {
                $key = $item->$other_get_method();
                // $item->$other_get_method may return an instance of this model.
                // If so, get its ID.
                if ($is_other_model) {
                    $key = $key->$this_id_method();
                }
                return $key;
            }
        );
        $is_multiple       = !empty($join_identifier['multiple']);
        $this_set_method   = isset($options['set_method']) ? $options['set_method'] : $this->getSetMethodName($this_mapping['property']);
        $other_id_method   = $join_mapper->getGetIdentifierMethodName();
        foreach ($collection as $item) {
            // Is this a multiple join? If so, how do I know?
            $item_property = $item->$this_get_method();
            if ($is_this_model) {
                $item_property = $item_property->$other_id_method();
            }
            $this_model_join_list = $grouped_join_list->getOffset($item_property);
            if ($this_model_join_list instanceof Collection) {
                $join_item = $this_model_join_list;
                if (!$is_multiple) {
                    $join_item = $this_model_join_list->first();
                }
                $item->$this_set_method($join_item);
            }
        }
    }

    /**
     * @brief calls save(), but forces create by nulling the identifier field.
     *
     * @param $object
     *
     * @return array<int>
     */
    public function create($object)
    {
        $collection = $this->getModelAsCollection($object);
        foreach ($collection as $model) {
            $this->setModelProperty($model, $this->identifier['property'], null);
        }
        return $this->save($collection);
    }


    /**
     * @brief Saves an object to the database. Updates or inserts
     *
     * @param $object
     *
     * @return array<int>|bool
     */
    public function save($object)
    {
        $collection  = $this->getModelAsCollection($object);
        $tbl         = $this->table;
        $colString   = $this->getColumnString();
        $plcString   = $this->getPlaceholderString();
        $updString   = $this->getUpdateString();
        $statement   = $this->database->prepare(
            "
			INSERT INTO `$tbl` ($colString)
			VALUES ($plcString)
			ON DUPLICATE KEY UPDATE $updString;
		"
        );
        $affectedIDs = array();
        $pdoMethods  = array();
        //
        // Trigger all on collection before attempting to run?
        // Must for now, because otherwise transactions would be nested.
        foreach ($collection as $model) {
            if (!($this->triggerCallbacks('preSave', $model))) {
                return false;
            }
        }
        $identifier      = $this->getIdentifier();
        $id_property     = $identifier['property'];
        $existingObjects = array();
        if (isset($identifier['column'])) {
            // retrieve the objects as they currently exist so we can perform a diff if we need to
            $collectionIds = array();
            // retrieve the IDs in a dehydrating fashion, in case they're models
            foreach ($collection as $object) {
                $collectionIds[] = $this->getModelProperty($object, $id_property);
            }
            foreach ($this->findByColumn($identifier['column'], $collectionIds, ['deleted' => true]) as $obj) {
                // index by ID
                $existingObjects[$this->getModelProperty($obj, $id_property)] = $obj;
            }
        }
        $this->database->beginTransaction();
        foreach ($collection as $i => $model) {
//			$this->bindParamsForSave($model, $statement);
            if ($statement->execute($this->dehydrate($model))) {
                // take note of whether this was an insert or update
                $m = $statement->rowCount();
                switch ($m) {
                    case self::PDO_RESULT_INSERT:
                        $pdoMethods[$i] = self::METHOD_CREATE;
                        break;
                    case self::PDO_RESULT_UPDATE:
                        $pdoMethods[$i] = self::METHOD_UPDATE;
                        break;
                    default:
                        $pdoMethods[$i] = null;
                        break;
                }
                if ($id_property) {
                    if (!$this->getModelProperty($model, $id_property)) {
                        $new_id = $this->database->lastInsertId();
                        $this->setModelProperty($model, $id_property, $new_id);
                    }
                    $affectedIDs[] = $this->getModelProperty($model, $id_property);
                }
                // also update n:n relation tables if they exist
                foreach ($this->getMap() as $map) {
                    if (empty($map['join'])
                        || empty($this->joins[$map['join']]['multi'])
                        || $this->joins[$map['join']]['multi']['read_only']
                    ) {
                        // not interested
                        continue;
                    }
                    $joinSchema = $this->joins[$map['join']];
                    $joinMulti  = $joinSchema['multi'];
                    $thisKey    = $this->getModelProperty($model, $this->getPropertyFromColumn($joinSchema['this_key']));
                    // step 1: delete existing relations in join table
                    $deleteRelationStatement = $this->database->prepare(
                        "
						DELETE FROM `{$joinMulti['via_table']}`
						WHERE `{$joinMulti['this_key']}` = :this_key
					"
                    );
                    $deleteRelationStatement->bindValue(':this_key', $thisKey);
                    if (!$deleteRelationStatement->execute()) {
                        $this->database->rollBack();
                        return false;
                    }
                    // step 2: insert new relations in join table
                    $insertRelationStatement = $this->database->prepare(
                        "
						INSERT IGNORE INTO `{$joinMulti['via_table']}`
							(`{$joinMulti['this_key']}`,`{$joinMulti['other_key']}`)
						VALUES
							(:this_key, :other_key)
					"
                    );
                    // should dehydrate to an array
                    $propertyValue = (array)$this->getModelProperty($model, $map['property']);
                    // iterate the values given and insert into join table
                    foreach ($propertyValue as $otherKey) {
                        $success = $insertRelationStatement->execute(
                            array(
                                'this_key'  => $thisKey,
                                'other_key' => $otherKey
                            )
                        );
                        if (!$success) {
                            $this->database->rollBack();
                            return false;
                        }
                    }
                }
            } else {
                $this->database->rollBack();
                return false;
            }
        }
        $this->database->commit();
        foreach ($collection as $i => $model) {
            $previous = null;
            if ($id_property && ($id = $this->getModelProperty($model, $id_property))) {
                $previous = array_key_exists($id, $existingObjects) ? $existingObjects[$id] : null;
                $this->triggerCallbacks('postSave', $model, $previous);
            }
            $this->raiseNotification($model, $pdoMethods[$i], $previous);
        }
        return $affectedIDs;
    }


    /**
     * Setup method. Implementing classes to define their SQL map here.
     * @return void
     */
    protected abstract function mapping();


    protected function hasTable($table)
    {
        if (!is_string($table)) {
            return false;
        }
        $this->table = $table;
        return true;
    }


    /**
     * @deprecated use self::hasPrototype() instead
     * @param string $model
     * @return bool
     */
    protected function hasModel($model)
    {
        if (!is_string($model) || !class_exists($model, true)) {
            return false;
        }
        $this->model = $model;
        return true;
    }


    /**
     * @param object $prototype
     * @return bool
     */
    protected function hasPrototype($prototype)
    {
        if (!is_object($prototype)) {
            return false;
        }
        $this->prototype = $prototype;
        $this->model     = get_class($prototype);
        return true;
    }


    protected function hasModuleNotifier($classname)
    {
        if (!is_string($classname) || !class_exists($classname, true)) {
            return false;
        }
        $this->moduleNotifier = $classname;
        return true;
    }

    /**
     * Adds a join to this mapper.
     *
     * @param string $name            The identifier for this join.
     * @param array  $join            A definition for this join, with these keys:
     *                                mapper:    a Mapper instance for the related table,
     *                                or the class name of a mapper
     *                                this_key:  the key for this side of the relation
     *                                other_key: the key for the other side of the relation
     *                                extra:     any extra conditions on the join
     *
     * @return bool
     * @throws Mapper\DefinitionException if $name contains a . symbol.
     *         Reason: getJoinMapper() can drill down into joined models, and .
     *         is the separator.
     */
    protected function hasJoin($name, array $join)
    {
        if (strpos($name, '.') !== false) {
            throw new Mapper\DefinitionException("Join name \"$name\" may not contain . characters");
        }
        $this->joins[$name] = $join;
        return true;
    }

    /**
     * Retrieves the definition that this join was set up with.
     *
     * @param string $name The identifier for this join.
     *                     Definitions can be retrieved on mappers joined to
     *                     this one by separating names with . characters.
     *
     * @return Mapper
     * @throws Mapper\DefinitionException if join $name isn't on this mapper
     */
    protected function getJoinDefinition($name)
    {
        $parts    = explode('.', $name, 2);
        $basename = array_shift($parts);
        if (!array_key_exists($basename, $this->joins)) {
            throw new Mapper\DefinitionException("Join \"$basename\" does not exist on class \"" . get_called_class(
            ) . "\"");
        }
        if (empty($parts)) {
            return $this->joins[$basename];
        } else {
            return $this->getJoinMapper($basename)->getJoinDefinition(array_shift($parts));
        }
    }

    /**
     * Retrieves the mapper that this join uses.
     *
     * @param string $name The identifier for this join.
     *
     * @return Mapper
     * @throws Mapper\DefinitionException if join $name isn't on this mapper
     */
    public function getJoinMapper($name)
    {
        $parts    = explode('.', $name, 2);
        $basename = array_shift($parts);
        if (!array_key_exists($basename, $this->joins)) {
            throw new Mapper\DefinitionException("Join \"$basename\" does not exist on class \"" . get_called_class(
            ) . "\"");
        }
        $mapperClass = $this->joins[$basename]['mapper'];
        if (!($mapperClass instanceof Mapper) && class_exists($mapperClass)) {
            $mapper = call_user_func(array($mapperClass, 'getInstance'));
        } else {
            $mapper = $this->joins[$basename]['mapper'];
        }
        if (empty($parts)) {
            return $mapper;
        } else {
            return $mapper->getJoinMapper(array_shift($parts));
        }
    }

    /**
     * Retrieves SQL for building a join that has been defined in hasJoin().
     *
     * @param string $name        The join's identifier as passed in hasJoin().
     * @param string $this_alias  The alias to use for this side of the join,
     *                            defaults to this mapper's table name.
     * @param string $other_alias The alias to use for the other side of the
     *                            join, defaults to other mapper's table name.
     * @param bool $joinDeleted   If true, soft-deleted rows in the joined table
     *                            will not be filtered out.
     *                            Defaults to false, as usually you want to
     *                            ignore the existence of soft-deleted rows.
     *
     * @return string
     * @throws Mapper\DefinitionException if join $name isn't on this mapper
     */
    public function getJoinSql($name, $this_alias = '', $other_alias = '', $joinDeleted = false)
    {
        $join         = $this->getJoinDefinition($name);
        $this_table   = $this->getTable();
        $other_mapper = $this->getJoinMapper($name);
        $other_table  = $other_mapper->getTable();
        if (strlen($this_alias) === 0) {
            $this_alias = $this_table;
        }
        if (strlen($other_alias) === 0) {
            $other_alias = $other_table;
        }
        $params = array(
            ':this_alias'  => $this_alias,
            ':this_key'    => $join['this_key'],
            ':other_table' => $other_table,
            ':other_alias' => $other_alias,
            ':other_key'   => $join['other_key'],
        );
        if (isset($join['multi'])) {
            // LEFT JOIN ...
            $join_sql                 = '`:via_table`   AS `:via_alias`   ON `:this_alias`.`:this_key`   = `:via_alias`.`:via_this_key`
			LEFT JOIN    `:other_table` AS `:other_alias` ON `:other_alias`.`:other_key` = `:via_alias`.`:via_other_key`';
            $params[':via_table']     = $join['multi']['via_table'];
            $params[':via_this_key']  = $join['multi']['this_key'];
            $params[':via_other_key'] = $join['multi']['other_key'];
            $params[':via_alias']     = "v_$other_alias";
        } else {
            $join_sql = '`:other_table` AS `:other_alias` ON `:this_alias`.`:this_key` = `:other_alias`.`:other_key`';
        }
        if (!$joinDeleted) {
            $filterSoftDeleteSql = $other_mapper->getSoftDeleteFilterSql($other_alias);
            if (isset($filterSoftDeleteSql)) {
                $join_sql .= ' AND '.$filterSoftDeleteSql;
            }
        }
        if (isset($join['extra']) && strlen($join['extra']) > 0) {
            $join_sql .= ' AND ' . $join['extra'];
        }
        return strtr($join_sql, $params);
    }

    /**
     * Hydrates a row from the database into a model.
     *
     * @param Traversable|array $rowList  The rows from the database.
     * @param string            $alias    The prefix of this mapper's table cells in $row
     * @param callable          $callback A callback to apply on all models (@see $this->hydrate)
     *
     * @return Collection
     */
    public function hydrateList($rowList, $alias = '', $callback = null)
    {
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
     * @throws Exception
     * @return object
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {
        $model = $this->createModel();
        foreach ($this->map as $map) {
            $propertyName = $map['property'];
            if (empty($map['column'])) {
                // check for multijoin
                if (!(isset($map['join']) && isset($this->joins[$map['join']]['multi']))) {
                    throw new Exception("Column must be declared if no multijoin set for it: " . json_encode($map));
                }
            } else {
                $columnName = $map['column'];
                if (!empty($alias)) {
                    $columnName = "$alias.$columnName";
                }
                $columnValue = array_key_exists($columnName, $row) ? $row[$columnName] : null;
                $this->setModelProperty($model, $propertyName, $columnValue);
            }
        }
        if (!empty($callback) && is_callable($callback)) {
            $model = call_user_func($callback, $this, $model, $row);
        }
        return $model;
    }

    /**
     * Helper method for hydrating a row through a join that has been set up
     * with $this->hasJoin().
     *
     * @see $this->hydrate()
     *
     * @param array    $row      The row received from the database
     * @param string   $join     The name of the join on this mapper
     * @param string   $alias    The alias that the joined table was given
     * @param callable $callback An optional callback
     *
     * @return object
     */
    public function hydrateJoin($row, $join, $alias = '', $callback = null)
    {
        $mapper = $this->getJoinMapper($join);
        if ($mapper instanceof Mapper) {
            return $this->getJoinMapper($join)->hydrate($row, $alias, $callback);
        }
        return null;
    }


    /**
     * Helper method for hydrating a collection of stub model objects.
     * Assumes that you have provided a collection of models that apply to this mapper
     *
     * @param Collection $collection
     * @param array      $identifier an optional array of 'column' and 'property' used to perform lookup
     *
     * @return Collection
     */
    public function hydrateCollection($collection, $identifier = null)
    {
        $identifier = $identifier ? : $this->getIdentifier();
        return $this->findByColumn($identifier['column'], $collection->getColumnValues($identifier['property']));
    }

    /**
     * Helper method used by methods for finding a single model - this throws an
     * exception if the model is deleted (it is an ISoftDeletable and isDeleted()
     * returns true)
     *
     * @throws Exception\Deleted if the model is marked as deleted.
     *
     * @param  object $model
     */
    protected function throwIfDeleted($model)
    {
        if ($model instanceof ISoftDeletable && $model->isDeleted()) {
            $e = new Exception\Deleted();
            $e->setDeletedObject($model);
            throw $e;
        }
    }

    /**
     * Retrieves an SQL fragment for filtering out or setting soft-deleted items.
     *
     * @param         $value
     * @param  string $alias The alias used for this table, if any.
     *
     * @return string
     */
    protected function getSoftDeleteSql($value, $alias = null)
    {
        if (isset($this->soft_delete_column)) {
            $column = "`{$this->soft_delete_column}`";
            if (isset($alias)) {
                $column = "`$alias`.$column";
            } else {
                $column = "{$this->getTable()}.$column";
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
            return "$column = " . $this->database->quote($value);
        }
        return null;
    }

    /**
     * Retrieves an SQL fragment for filtering out soft-deleted items.
     *
     * @param  string $alias The alias used for this table, if any.
     *
     * @return string
     */
    protected function getSoftDeleteFilterSql($alias = null)
    {
        return $this->getSoftDeleteSql($this->soft_delete_undeleted_value, $alias);
    }

    /**
     * Retrieves an SQL fragment for updating soft-deleted items.
     *
     * @param string $alias
     *
     * @return string
     */
    protected function getSoftDeleteUpdateSql($alias = null)
    {
        return $this->getSoftDeleteSql($this->soft_delete_deleted_value, $alias);
    }


    /**
     * Creates a new instance of class with name passed in $this->hasModel().
     *
     * @param int $idValue
     * @throws Mapper\Exception
     * @return object
     */
    public function createModel($idValue = null)
    {
        if ($this->prototype) {
            // prototype has higher precedence than model class
            $model = clone $this->prototype;

            // iterate the map and clone any objects in there
            foreach ($this->map as $map) {
                $getProp = $this->getGetMethodName($map['property']);
                $setProp = $this->getSetMethodName($map['property']);

                // only clone if it's actually an object
                if (is_object($value = $model->$getProp())) {
                    $model->$setProp(clone $value);
                }
            }
        }
        elseif ($this->model) {
            // TODO model is deprecated, this case is to be removed
            // create a new, blank model if one is defined
            $model = new $this->model;
        }
        else {
            throw new Exception("Cannot create nonexistent model -> {$this->model}");
        }

        if ($idValue) {
            $this->setModelProperty($model, $this->identifier['property'], $idValue);
        }

        return $model;
    }

    /**
     * @brief Returns an SQL fragment containing a comma separated list of column names possibly aliased
     *
     * @param String $alias
     * @param array  $whitelist If not empty, a whitelist of column names to use
     *
     * @return String
     */
    public function getColumnString($alias = '', $whitelist = array())
    {
        $columnList = array();
        foreach ($this->map as $map) {
            if (empty($map['column'])) {
                continue;
            }
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
     * Helper method for getting a column string from a join.
     *
     * @see $this->getColumnString()
     *
     * @param string $join  The name of the join on this mapper
     * @param string $alias The alias to give this join.
     *
     * @return string
     */
    public function getJoinColumnString($join, $alias = '')
    {
        return $this->getJoinMapper($join)->getColumnString($alias);
    }

    /**
     * @brief Returns a comma separated list of PDO placeholders. Works with Mapper::getColumnString
     * @return String
     */
    public function getPlaceholderString()
    {
        $placeholderList = array();
        foreach ($this->map as $map) {
            if (empty($map['column'])) {
                continue;
            }
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
    public function getArrayPlaceholders($input, $prefix = null)
    {
        $placeholders = array();
        foreach ($input as $i => $param) {
            $placeholders[] = isset($prefix) ? (':' . $prefix . '_' . $i) : '?';
        }
        return '(' . implode(',', $placeholders) . ')';
    }

    /**
     * Flattens a list of parameters for an array.
     *
     * Used in conjunction with getArrayPlaceholders() - calling this with the
     * same input and prefix will generate params and placeholders that work
     * with each other.
     *
     * @param array  $input
     * @param string $prefix If not supplied, ? will be assumed as placeholder
     *                       for all values.
     *
     * @return array
     */
    public function getArrayParameters($input, $prefix = null)
    {
        $params = array();
        foreach ($input as $i => $param) {
            if (isset($prefix)) {
                $params[$prefix . '_' . $i] = $param;
            } else {
                $params[] = $param;
            }
        }
        return $params;
    }

    /**
     * @brief Returns an SQL fragment containg the assignment portion of an UPDATE query
     * @return String
     */
    public function getUpdateString()
    {
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
            if (empty($map['column'])) {
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
     * Inverse of hydrate(). Converts a model to an array with indices matching the db fields.
     *
     * @param object $model              The model to get values from
     * @param bool   $includeTimestamps  Whether or not to include timestamps in the dehydrated output
     * @throws Mapper\Exception if the map is not valid
     * @return array
     */
    public function dehydrate($model, $includeTimestamps = false)
    {
        $result = array();
        foreach ($this->map as $map) {
            if (empty($map['column'])) {
                // if column is empty, ensure a join is specified, and that it is a multijoin
                $join_name = isset($map['join']) ? $map['join'] : '';
                if (isset($this->joins[$join_name]['multi'])) {
                    continue;
                }
                // if the join doesn't exist, or not multijoin, failhard
                throw new Exception("Column must be declared if no multijoin set for it: " . json_encode($map));
            }
            $propertyName = $map['property'];
            $columnName   = $map['column'];
            //
            // Timestamp fields will never be bound.
            if ($includeTimestamps || !(isset($map['updated_timestamp']) || isset($map['created_timestamp']))) {
                $result[$columnName] = $this->getModelProperty($model, $propertyName);
            }
        }
        return $result;
    }

    /**
     * @brief Converts an underscore separated string to camel case
     *
     * @param String $string
     *
     * @return String
     */
    protected function toCamelcase($string)
    {
        $segmentList = explode('_', $string);
        foreach ($segmentList as &$segment) {
            $segment = ucfirst($segment);
        }
        return implode($segmentList);
    }

    /**
     * @brief Explodes a camel case string apart at the humps using underscores
     *
     * @param String $string
     *
     * @return String
     */
    public function toUnderscore($string)
    {
        $string      = str_replace('_', '', $string);
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
    public function registerCallback($point, $method)
    {
        $this->callback_queue[$point][] = $method;
        return $this;
    }

    public function triggerCallbacks($point)
    {
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


    /**
     * If possible, raise a notification
     *
     * @param object $object
     * @param string $verb
     * @param object $previous
     *
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        if (!isset($this->sessionData['user'])) {
            return false;
        }
        $diff = $previous && ($verb != self::METHOD_DELETE) ? $this->diff($object, $previous) : null;
        // create a new event, and send it to the scheduler
        $event = new Event($this->sessionData['user'], $verb, $object, $this, $diff);
        $this->getNotifier()->setTopic(Notif::TOPIC_APPLICATION)->notice(serialize($event));
        return true;
    }

    /**
     * Compares two objects mapped by this mapper and returns an array of the differences between.
     * Does not traverse hydrations - it explicitly dehydrates and compares ID values only.
     *
     * If $objectB is not supplied, diff will attempt to compare to the version of the object
     * currently stored in the database
     *
     * @param object $objectA
     * @param object $objectB
     *
     * @throws Mapper\Exception
     * @return array
     */
    public function diff($objectA, $objectB = null)
    {
        $a = trim(get_class($objectA), '\\');
        $c = trim($this->getModel(), '\\');
        if ($a != $c) {
            throw new Exception("Cannot diff objects from another mapper: require '$c', got '$a'.");
        }
        if ($objectB) {
            $b = trim(get_class($objectB), '\\');
            if ($a != $b) {
                throw new Exception("Cannot diff objects of different classes: '$a' != '$b'.");
            }
        } else {
            // retrieve current DB version
            $objectB = $this->findById($this->getModelProperty($objectA, $this->identifier['property']));
        }

        $diff = array();
        foreach ($this->getMap() as $mapping) {
            $property = $mapping['property'];
            /** @var \Schoolbox\Column\IColumnType $hydrator */
            $hydrator = $mapping['hydrator'];
            $valueA   = $this->getModelProperty($objectA, $property);
            $valueB   = $this->getModelProperty($objectB, $property);
            // dehydrate the values
            $dhA = $hydrator->dehydrate($valueA, $this);
            $dhB = $hydrator->dehydrate($valueB, $this);
            // can now do primitive comparison on the dehydrated values (should be in the same type)
            if ($dhA !== $dhB) {
                $diff[$property] = array($dhA, $dhB);
            }
        }
        return $diff;
    }

    /**
     * String representation of object
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        // we don't need to serialize anything other than the name of the class
        return '';
    }

    /**
     * Constructs the object
     *
     * @param string $serialized
     *
     * @return void
     */
    public function unserialize($serialized)
    {
        // don't need to do anything but run the constructor
        $this->__construct();
    }

    /**
     * Truncate
     */
    public function truncate()
    {
        $query     = "TRUNCATE `{$this->table}`";
        $statement = $this->database->prepare($query);
        $statement->execute();
    }
}
