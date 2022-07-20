<?php

/**
 * Base mapper
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox;

use Alaress\Cache;
use Alaress\Database\Predicate\Builder;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Database\Quoter\IQuoter;
use Alaress\DebugBar\Instance as DebugBar;
use Alaress\IDatabase;
use Alaress_Date_Format;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Column;
use Schoolbox\Column\Factory as ColumnFactory;
use Schoolbox\Column\IColumnType;
use Schoolbox\Column\IFactory;
use Schoolbox\Column\Model as ModelColumn;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\Di\Configurator;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Performance\DevMonitor;
use Schoolbox\Mapper\Performance\IMonitor;
use Schoolbox\Mapper\Performance\LiveMonitor;
use Schoolbox\Realtime\Schedule\Broker;
use Schoolbox\Realtime\Schedule\Event\NotifierInflector;
use Schoolbox\Realtime\Schedule\Event\Splitter;
use Schoolbox\Realtime\Schedule\Trigger\IPropagator;
use Schoolbox\Realtime\Schedule\Trigger\Propagator;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Realtime\Schedule\Trigger\TriggerFactoryFactory;
use Serializable;
use Traversable;
use TypeError;
use UnderflowException;
use UnexpectedValueException;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;

abstract class Mapper implements IMapper, Serializable
{
    /**
     * Represents a boolean data type.
     */
    public const TYPE_BOOLEAN = 5;

    /**
     * Represents an almost-boolean data type: enum('true','false') in the database
     */
    public const TYPE_BOOL_ENUM = 6;

    /**
     * Represents the SQL SET data type.
     */
    public const TYPE_SET = 7;

    /**
     * Represents the SQL NULL data type.
     */
    public const TYPE_NULL = 0;

    /**
     * Represents the SQL INTEGER data type.
     */
    public const TYPE_INTEGER = 1;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     */
    public const TYPE_STRING = 2;

    /**
     * Represents the SQL large object data type.
     */
    public const TYPE_LOB = 3;

    public const TYPE_DATETIME   = 10;
    public const TYPE_DATE       = 11;
    public const TYPE_TIME       = 12;
    public const TYPE_TIMESTAMP  = 13;
    public const TYPE_TIMESPAN   = 14;
    public const TYPE_DECIMAL    = 20;
    public const TYPE_SERIALIZED = 30;
    public const TYPE_JSON       = 31;
    public const TYPE_MODEL      = 40;
    public const TYPE_COLLECTION = 41;
    public const TYPE_COLOR      = 50;

    public const METHOD_CREATE = 'create';
    public const METHOD_READ   = 'read';
    public const METHOD_UPDATE = 'update';
    public const METHOD_DELETE = 'delete';

    // as per http://www.php.net/manual/en/pdostatement.rowcount.php#109891
    public const PDO_RESULT_NOCHANGE = 0;
    public const PDO_RESULT_INSERT = 1;
    public const PDO_RESULT_UPDATE = 2;

    // Valid points are preSave, postSave, preDelete, postDelete
    public const CALLBACK_PRESAVE    = 'preSave';
    public const CALLBACK_POSTSAVE   = 'postSave';
    public const CALLBACK_PREDELETE  = 'preDelete';
    public const CALLBACK_POSTDELETE = 'postDelete';
    public const CALLBACK_PRESOFTDELETE  = 'preSoftDelete';
    public const CALLBACK_POSTSOFTDELETE = 'postSoftDelete';
    public const CALLBACK_PRERESTORE     = 'preSoftDeleteRestore';
    public const CALLBACK_POSTRESTORE    = 'postSoftDeleteRestore';

    // options for mapping methods
    public const COLUMN_IDENTIFIER        = 1;
    public const COLUMN_CREATED_TIMESTAMP = 2;
    public const COLUMN_UPDATED_TIMESTAMP = 4;
    public const COLUMN_JOIN_MULTI        = 8;
    public const COLUMN_JOIN_READONLY     = 16;
    public const COLUMN_JOIN_REVERSE      = 32;
    public const COLUMN_SOFTDELETE        = 64;
    public const COLUMN_UUID              = 128;
    public const COLUMN_IDENTIFIER_UUID   = self::COLUMN_IDENTIFIER | self::COLUMN_UUID;
    public const COLUMN_ENUM       = 256;  // May only be persisted as one of a set of declared values

    // Sort ordering
    public const SORT_ORDER_ASC  = 'asc';
    public const SORT_ORDER_DESC = 'desc';

    /**
     * @var IDatabase
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
     * Class name of the module notifier to associate with this mapper.
     *
     * @var string
     */
    protected $moduleNotifier;

    /**
     * Indexed by model property name (not db column)
     *
     * @see MappingCollection::getOffsetByColumn() for O(1) fetch by column
     *
     * @var MappingCollection
     */
    protected $map;

    /**
     * Valid enum values. Indexed by [propertyName]
     *
     * @var string[][]
     */
    private $enums = [];

    /**
     * A list of joins that this mapper has had defined.
     *
     * @var array
     */
    protected $joins = array();

    /**
     * Will be NULL if entity does not have an identifier
     *
     * @var array|null
     */
    protected $identifier;

    /**
     * 'created' timestamp map (if registered in mapping())
     *
     * @var null|array
     */
    private $createdMap;

    /**
     * 'updated' timestamp map (if registered in mapping())
     *
     * @var null|array
     */
    private $updatedMap;

    /**
     * Callbacks that are called on certain events within this mapper.
     *
     * @var array
     */
    protected $callback_queue = array();

    /**
     * Stores the singleton instance of the mapper
     *
     * @var static[]
     */
    protected static $instance;

    /** @var IFactory */
    protected static $columnFactory;

    /** @var IMonitor */
    protected static $perfmon;

    /** @var IPropagator */
    protected static $prop;

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
     * Session data.
     *
     * TODO decouple the dependency between mappers and session
     *
     * @var array
     */
    protected $sessionData = [];

    /** @var Cache|GenericCollection[] */
    private $selectCache;

    /** @var Cache */
    private $joinMapperCache;

    /** @var Cache */
    protected $foreignBodyCache;

    /** @var Cache */
    protected $localBodyCache;

    /** @var IMonitor */
    protected $monitor;

    /** @var Propagator */
    protected $propagator;

    /** @var Propagator */
    private $propagatorSuppressed;

    /**
     * If this is TRUE, and a caller requests an entity that has already been fetched, it will return
     * the previously fetched object instance. Otherwise it will return a new model.
     *
     * @var bool
     */
    private $localBodyCacheBypass = false;

    /**
     * Is caching enabled for this mapper?
     * Affects only hydrate and select cache.
     *
     * @var bool
     */
    protected $cacheEnabled = true;

    /**
     * Reference to global DI container; lord forgive me for what I have done
     *
     * @deprecated
     * @var ContainerInterface
     */
    private static $di;

    /**
     * @param ContainerInterface $di
     * @return void
     */
    public static function di(ContainerInterface $di)
    {
        self::$di = $di;
        self::$columnFactory = new ColumnFactory();

        // only functional in development

        // This line raises an error on CI, where INSTANCE_ID is always "ci":
        // it doesn't raise an error on Vagrant where INSTANCE_ID comes from
        // an external config, so ignore the "no error to ignore" message here.
        /* @phpstan-ignore-next-line */
        self::$perfmon = (INSTANCE_ID === 'vagrantx' && self::isWithinWebRequest())
            ? new DevMonitor(DebugBar::getInstance())
            : new LiveMonitor()
        ;
    }


    /**
     * @inheritdoc
     */
    public function isSoftDeletable(): bool
    {
        // Default behaviour is whether this mapper has a soft-delete column registered
        return (bool)$this->soft_delete_column;
    }


    /**
     * @deprecated under pain of death DO NOT USE THIS; refactor the Mapper instead
     * @return ContainerInterface
     */
    public static function getDi()
    {
        return self::$di;
    }

    /**
     * Is this mapper being instantiated within a web request?
     *
     * The performance monitor is always off for daemon/CLI requests.
     *
     * @return bool
     */
    protected static function isWithinWebRequest()
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        if (!isset($_SERVER['REQUEST_METHOD'])) {
            return false;
        }
        return true;
    }

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
            self::$instance[$class_name]->postSetup();
        }
        return self::$instance[$class_name];
    }

    /**
     * Overrides constructor as public, so that new instances can be created
     * without going through getInstance().
     */
    public function __construct()
    {
        $this->setupDatabase();
        $this->map = new MappingCollection();

        $this->initCaches();
        // Switch on the cache if this is a web request: otherwise switch off
        if (self::isWithinWebRequest()) {
            $this->enableCache();
        } else {
            $this->disableCache();
        }
        $this->setConfigLoader($this->buildConfigLoader());
        $this->setup();

        // If for some reason self::di() wasn't called before this constructor:
        $this->setColumnFactory(self::$columnFactory ?: new ColumnFactory());
        $this->setMonitor(self::$perfmon ?: new LiveMonitor());

        // Need to add session data so that notifiers can do their thing
        $this->setSessionData($_SESSION ?? []);
        $this->mapping();

        $this->monitor->cacheIndices($this);
    }

    /**
     * Helper method for building a config loader.
     *
     * @return IConfigLoader
     */
    protected function buildConfigLoader()
    {
        // TODO SA-8354: disentangle circular dependency between mapper and config
        return new ConfigLoader();
    }

    /**
     * The column that is used for storing if this field has been soft deleted.
     *
     * @var string|null
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
     * Installs a database into this mapper.
     *
     * Override with a blank method for mappers which don't have databases?
     */
    protected function setupDatabase()
    {
        $this->database = \Database::getInstance();
    }

    /**
     * Default setup for any other objects which compose the mapper.
     */
    protected function setup()
    {
    }

    /**
     * Secondary hook method which occurs after successful construction
     *
     * This was made because a subclass of Mapper needed to make a
     * db call, but that needs to occur after mapping() since it
     * defines models and tables (unavailable in setup() atm)
     */
    protected function postSetup()
    {
    }


    /**
     * @return void
     */
    protected function initCaches()
    {
        // a cache of SELECT results
        $this->selectCache = new Cache(function (PDOStatement $statement, $bypassCache = false) {
            $statement->execute();

            // bypass the local body cache if necessary
            $this->localBodyCacheBypass = $bypassCache;

            $collection = $this->hydrateSelect($statement);

            // turn that cache back on!
            $this->localBodyCacheBypass = false;

            return $collection;
        });

        // a cache of join mappers (for some reason this method is expensive)
        $this->joinMapperCache = new Cache(function ($name) {
            $parts    = explode('.', $name, 2);
            $basename = array_shift($parts);

            if (!array_key_exists($basename, $this->joins)) {
                throw new Mapper\DefinitionException("Join '$basename' does not exist on class '" . get_called_class() . "'");
            }

            $mapperClass = $this->joins[$basename]['mapper'];

            if (!($mapperClass instanceof Mapper) && class_exists($mapperClass)) {
                /** @var Mapper $mapper */
                $mapper = call_user_func(array($mapperClass, 'getInstance'));
                $mapper->setConfigLoader($this->getConfigLoader());
            } else {
                $mapper = $this->joins[$basename]['mapper'];
            }

            if (empty($parts)) {
                return $mapper;
            }

            return $mapper->getJoinMapper(array_shift($parts));
        });

        // cache of foreign entities retrieved via this mapper
        // they SHOULD be the same object in memory as those found in local on the equivalent sub-mapper
        $this->foreignBodyCache = new Cache(function (Mapper $foreignMapper, array $row, $alias = '') {
            return $foreignMapper->hydrate($row, $alias);
        });

        // cache of local entities
        $this->localBodyCache = new Cache(function (Mapper $mapper, array $row, $alias = '') {

            // Does this model lack an identifier?
            if (!$this->hasIdentifier()) {
                // Examine each column
                $nonNullFound = false;
                foreach ($mapper->map as $map) {
                    $columnName = trim("$alias.{$map['column']}", '.');
                    if (null !== $row[$columnName]) {
                        $nonNullFound = true;
                        break;
                    }
                }
                // Were all columns null ?
                if (!$nonNullFound) {
                    // Return scalar null
                    return null;
                }
            }

            $key = trim("$alias.{$mapper->getIdentifierColumn()}", '.');

            if (array_key_exists($key, $row) && $this->localBodyCacheBypass == false) {
                $id = $row[$key];

                // If the identifier is null, just return scalar NULL.
                if (null === $id) {
                    return null;
                }
                $model = $mapper->createModel($id);
            } else {
                $model = $mapper->createModel();
            }

            foreach ($mapper->map as $map) {
                $propertyName = $map['property'];

                // We don't hydrate the model for:
                // * many-many join (Where this entity A and other B have a N:N cardinality)
                // * reverse multi-join (Where this entity A and other B have a 1:N cardinality)
                if (empty($map['column'])) {
                    // check for multijoin
                    if (!(isset($map['join']) && isset($mapper->joins[$map['join']]['multi']))) {
                        throw new Exception("Column must be declared if no multijoin set for it: " . json_encode($map));
                    }
                    // Don't automatically hydrate reverse joins. Reverse joins must be hydrated explicitly.
                } elseif (!(isset($map['join']) && ($map['join_def']['reverse'] ?? false))) {
                    $columnName = $map['column'];

                    if (!empty($alias)) {
                        $columnName = "$alias.$columnName";
                    }

                    $columnValue = $row[$columnName] ?? null;

                    $mapper->setModelProperty($model, $propertyName, $columnValue);
                }
            }

            return $model;
        });
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
     * @param  IDatabase $database
     *
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @return IDatabase
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
     * Get a list of all the joins specified in this mapper
     *
     * @return array
     */
    public function getJoin()
    {
        return $this->joins;
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
     * Returns the session user if one exists (won't in Daemons and CLI Tools)
     *
     * @return User
     */
    public function getSessionUser(): ?User
    {
        return $this->sessionData['user'] ?? null;
    }


    /**
     * @param IMonitor $monitor
     */
    protected function setMonitor(IMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    /**
     * @return IPropagator
     */
    public function getEventPropagator()
    {
        if (!$this->propagator) {
            if (!self::$prop) {
                // We don't have a propagator setup, lets connect to the rabbit
                try {
                    self::$prop = new Propagator(
                        new Splitter(
                            new NotifierInflector(
                                new Configurator($this->getDi()),
                            ),
                            new TriggerFactoryFactory(true),
                        ),
                        new Broker(
                            INSTANCE_ID,
                            $this->getDi()->get('amqp'),
                            $this->getDi()->get('redis.storage'),
                        ),
                        INSTANCE_ID,
                    );

//                    self::$prop = $this->getDi()->newInstance(
//                        Propagator::class,
//                        [
//                            'splitter'   => $this->getDi()->lazyNew(
//                                Splitter::class,
//                                [
//                                    'inflector' => $this->getDi()->lazyNew(NotifierInflector::class, [
//                                        'configurator' => $this->getDi()->lazyNew(Configurator::class, [
//                                            'di' => $this->getDi()
//                                        ])
//                                    ]),
//                                    'factory'   => $this->getDi()->lazyNew(TriggerFactoryFactory::class, [true]),
//                                ]
//                            ),
//                            'broker'     => $this->getDi()->lazyNew(
//                                Broker::class,
//                                [
//                                    'instanceId' => INSTANCE_ID,
//                                    'amqp'       => $this->getDi()->lazyGet('amqp'),
//                                    'redis'      => $this->getDi()->lazyGet('redis.storage'),
//                                    // no 'stomp'
//                                ]
//                            ),
//                            'instanceId' => INSTANCE_ID,
//                        ]
//                    );
                } catch (NotFoundExceptionInterface $e) {
                    // in PHPUnit land, we do not have external services.
                    // this catch is merely to accommodate that case, it should
                    // not be relied upon in development
                    self::$prop = new MockPropagator();
                } catch (\ErrorException | AMQPIOException | AMQPRuntimeException $e) {
                    // Could not connect to the propagator's AMQP instance
                    // (i.e. RabbitMQ).
                    // Continue as if it didn't exist, but log a warning
                    trigger_error(
                        sprintf('Could not connect to AMQP instance: %s', $e->getMessage()),
                        E_USER_WARNING,
                    );
                    self::$prop = new MockPropagator();
                }
            }
            $this->propagator = self::$prop;
        }

        return $this->propagator;
    }

    /**
     * @param IPropagator $propagator
     * @return static
     */
    public function setEventPropagator(IPropagator $propagator = null)
    {
        $this->propagator = $propagator;
        return $this;
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
            return new Collection($object->getAll());
        }
        if (is_array($object)) {
            return new Collection($object);
        }
        return new Collection(array($object));
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
            $parentId = $node->$getParent()?->$getId();
            if (!$parentId) {
                $treeList->add($node);
            }
            $node->$setChildren(
                $collection
                    ->filter(
                        function ($childNode) use ($nodeId, $getParent, $getId) {
                            return $childNode->$getParent()?->$getId() === $nodeId;
                        }
                    )
                    ->reindex(),
            );
        }
        return $treeList;
    }

    /**
     * Loads all ancestors of this model or collection.
     *
     * Attempts to perform this action in as few queries as possible.
     *
     * @deprecated figure if this is actually required: this is complicated and
     *             seems expensive for something done on every curriculum load
     *
     * @param mixed  $object
     * @param string $parentField
     * @param int    $currentDepth
     *
     * @return mixed
     */
    public function loadAncestors(
        $object,
        $parentField = 'parent',
        $currentDepth = 1
    ) {
        // Recursive relation protection
        if ($currentDepth >= 50) {
            return $object;
        }
        $collection = $this->getModelAsCollection($object);
        $getId      = $this->getGetIdentifierMethodName();
        $getParent  = $this->getGetMethodName($parentField);
        $setParent  = $this->getSetMethodName($parentField);

        // Get parent ID of each $object
        $parentIds = [];
        foreach ($collection as $node) {
            $parent = $node->$getParent();
            if (!is_object($parent)) {
                break;
            }
            $parentId = $parent->$getId();
            if ($parentId) {
                $parentIds[] = $parentId;
            }
        }

        if (!empty($parentIds)) {
            // Load all parents of $collection in one query
            $parents = [];
            foreach (
                $this->loadAncestors(
                    $this->findByColumn($this->getIdentifierColumn(), $parentIds),
                    $parentField,
                    $currentDepth + 1
                ) as $parent
            ) {
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
     * @deprecated figure if this is actually required: this is complicated and
     *             used only once
     *
     * @param mixed  $object
     * @param string $parentField
     * @param string $childField
     * @param int    $currentDepth
     *
     * @return object
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
            foreach (
                $this->loadDescendants(
                    $this->findByColumn($this->getMap()->getOffset($parentField)['column'], $ids),
                    $parentField,
                    $childField,
                    $currentDepth + 1
                ) as $child
            ) {
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
        return $this->getGetMethodName($this->getIdentifierProperty());
    }

    /**
     * Gets name of set method for identifier column.
     *
     * @return string
     */
    public function getSetIdentifierMethodName()
    {
        return $this->getSetMethodName($this->getIdentifierProperty());
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
    public function getModelProperty($model, $property)
    {
        $get_method  = $this->getGetMethodName($property);
        $value       = $model->$get_method();
        $map         = $this->map->getOffset($property);
        if (!$map) {
            return $value;
        }
        $join_mapper = isset($map['join']) ? $this->getJoinMapper($map['join']) : null;
        if ($map['hydrator'] instanceof IColumnType) {
            $value = $map['hydrator']->dehydrate($value, $join_mapper);
        }
        return $value;
    }

    /**
     * Translates a column to a property field
     *
     * @param string $column
     * @return string
     */
    public function getPropertyFromColumn($column)
    {
        foreach ($this->map as $map) {
            if (array_key_exists('column', $map) && $map['column'] === $column) {
                return $map['property'];
            }
        }
        throw new InvalidArgumentException("Column not found: $column");
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
    public function setModelProperty($model, $property, $value)
    {
        $set_method  = $this->getSetMethodName($property);
        $map         = $this->map->getOffset($property);
        $join_mapper = isset($map['join']) ? $this->getJoinMapper($map['join']) : null;
        $hydrator    = $map['hydrator'] ?? null;
        if ($hydrator instanceof IColumnType) {
            $value = $hydrator->hydrate($value, $join_mapper);
            // Attempting to set a model value to NULL will likely break,
            // and won't do anything because it will be NULL on hydrate anyway.
            if (!isset($value) && $hydrator instanceof ModelColumn) {
                return $this;
            }
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
        if ($options & self::COLUMN_UUID) {
            $map['uuid'] = true;
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
     * @param string          $column
     * @param int|IColumnType $type
     * @param null|int        $options
     * @param null|string     $property
     * @param null|mixed      $params   Any extra params required for a special column type, eg permitted enum values
     * @return bool
     * @throws InvalidArgumentException
     */
    protected function uses($column, $type, $options = null, $property = null, $params = null)
    {
        if (!$this->hasMap($this->buildColumnMap($column, $type, $options, $property))) {
            return false;
        }

        if (self::COLUMN_ENUM & $options) {
            $this->registerEnum($this->getPropertyFromColumn($column), $params);
        }

        return true;
    }


    /**
     * The concise, type-safe version of hasMap() + hasJoin()
     *
     * @param string                $column         Name of field column in database
     * @param string                $foreignMapper  Foreign mapper used for hydrating foreign model
     * @param string                $foreignKey     Name of foriegn column we will join to in foreign table
     * @param IColumnType|null      $hydrator
     * @param int|null              $options
     * @param string|null           $property       Property of the model that this object will be attached to
     * @param string|null           $name           Name of the join used for referencing when retrieving join parameters
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

        if ($options & self::COLUMN_JOIN_REVERSE) {
            $map['join_def']['reverse'] = true;
        }

        return $this->hasMap($map);
    }

    /**
     * The concise, type-safe version of hasMap() + hasJoin(self::JOIN_REVERSE)
     *
     * Use this when a parent object has 0|1 relationship to a child object, where
     * the join is defined on the child's table.
     *
     * @param string      $foreignKey      The foreign key on the other table
     * @param string      $foreignMapper
     * @param string      $property
     * @param string      $name
     * @return bool
     */
    protected function joinsReverseSingle($foreignKey, $foreignMapper, $property = null, $name = null)
    {
        $localColumn = $this->getIdentifierColumn();
        $options = self::COLUMN_JOIN_REVERSE;
        return $this->joins($localColumn, $foreignMapper, $foreignKey, self::TYPE_MODEL, $options, $property, $name);
    }

    /**
     * The concise, type-safe version of hasMap() + hasJoin(self::JOIN_REVERSE | self::COLUMN_JOIN_MULTI)
     *
     * Use this when a parent object has a Collection of 0:N children objects associated with it, where
     * the join is defined on the children's table.
     *
     * @param string      $foreignKey      The foreign key on the other table
     * @param string      $foreignMapper
     * @param string      $property
     * @param string      $name
     * @return bool
     */
    protected function joinsReverseMulti($foreignKey, $foreignMapper, $property = null, $name = null)
    {
        $localColumn = $this->getIdentifierColumn();
        $options = self::COLUMN_JOIN_REVERSE | self::COLUMN_JOIN_MULTI;
        return $this->joins($localColumn, $foreignMapper, $foreignKey, self::TYPE_COLLECTION, $options, $property, $name);
    }


    /**
     * The concise, type-safe version of hasMap() + hasJoin(['multi'])
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
        if (!$localKey && !$this->hasIdentifier()) {
            throw new Exception("No identifier specified, either provide one or declare a primary key first");
        }
        $type = $hydrator ?: self::TYPE_COLLECTION;
        $map  = $this->buildColumnMap('', $type, $options, $property);
        // there is no local column in this instance
        unset($map['column']);
        $map['join'] = $viaTable;
        $map['join_def'] = [
            'mapper'    => $foreignMapper,
            'this_key'  => $localKey ?: $this->getIdentifierColumn(),
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
        } elseif (empty($map['type'])) {
            // hydrator specified, but no type: assume model
            $map['type'] = self::TYPE_MODEL;
        }

        if (empty($map['type'])) {
            // both hydrator and type cannot be empty
            return false;
        }

        // if property is empty, derive it from the column, converting snake_case to camelCase
        if (empty($map['property'])) {
            // if type is model, truncate '_id' suffix if it exists
            if (isset($map['type']) && $map['type'] === self::TYPE_MODEL && substr($map['column'], -3) === '_id') {
                $property = substr($map['column'], 0, -3);
            } else {
                $property = $map['column'];
            }
            $map['property'] = score2camel($property);
        }

        if (isset($map['identifier'])) {
            $this->setIdentifier($map);
        }

        if (isset($map['created_timestamp'])) {
            $this->setCreatedMap($map);
        }

        if (isset($map['updated_timestamp'])) {
            $this->setUpdatedMap($map);
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
     * @return GenericCollection
     */
    public function createCollection($contents = array())
    {
        return new GenericCollection($this->getModel(), $contents);
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
        return $this->hasSoftDeleteField($column, Alaress_Date_Format::now()->getDatabase(), null);
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
     * @param object|Collection $object
     *
     * @return bool
     * @throws Exception
     */
    public function delete($object)
    {
        if ($this->isSoftDeletable()) {
            return $this->softDelete($object);
        } else {
            return $this->hardDelete($object);
        }
    }

    /**
     * Soft deletes one or more models.
     *
     * Does not re-delete an already deleted item, nor call pre/post softDelete hooks, or trigger delete notifications.
     *
     * @param object|Collection $object Model or collection of models to delete
     *
     * @return bool
     */
    public function softDelete($object)
    {
        if (!isset($this->soft_delete_column)) {
            return false;
        }
        $collection = $this->getModelAsCollection($object);
        $notDeleted = $collection->filter(function (ISoftDeletable $model) {
            return !$model->isDeleted();
        });
        return $this->updateAll(
            $notDeleted,
            $this->soft_delete_column,
            $this->soft_delete_deleted_value,
            self::CALLBACK_PRESOFTDELETE,
            self::CALLBACK_POSTSOFTDELETE,
            self::METHOD_DELETE
        );
    }

    /**
     * Truly deletes an item.
     *
     * @param object|Collection $object Model or collection of models to delete
     *
     * @return bool
     * @throws Exception
     */
    public function hardDelete($object)
    {
        $collection = $this->getModelAsCollection($object);
        if (count($collection) === 0) {
            return false;
        }

        // Pre-delete callbacks
        foreach ($collection as $model) {
            if (!($this->triggerCallbacks(self::CALLBACK_PREDELETE, $model))) {
                return false;
            }
        }

        // Run update queries
        $this->database->beginTransaction();
        foreach ($this->buildPredicateBuildersForCollection($collection) as $pb) {
            // Build query
            $query     = "DELETE FROM `{$this->getTable()}` WHERE " . implode(' AND ', $pb->getWhere());
            $statement = $this->database->prepare($query);
            foreach ($pb->getParams() as $key => $id) {
                $statement->bindValue($key, $id);
            }

            // Run query
            try {
                if (!$statement->execute()) {
                    $this->database->rollBack();
                    return false;
                }
            } catch (PDOException $e) {
                $this->database->rollBack();
                throw $e;
            }
        }
        $this->database->commit();

        // Post-delete callbacks
        foreach ($collection as $model) {
            $this->triggerCallbacks(self::CALLBACK_POSTDELETE, $model);
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
     * @param string                    $columnName
     * @param string|string[]|int|int[] $columnValue
     * @param array                     $options
     * @return int
     */
    public function deleteByColumn($columnName, $columnValue, array $options = [])
    {
        return $this->deleteByColumnList(array($columnName => $columnValue), $options);
    }


    /**
     * Deletes (whether hard or soft) all records that match the given set of column values
     * Returns the number of records deleted.
     *
     * @param array $columnList
     * @param array $options
     * @return int
     */
    public function deleteByColumnList($columnList, array $options = [])
    {
        return $this->executeDelete($this->getDeleteString(), new Builder($this->getDatabase()->getQuoter(), $this->getTable()), $columnList, $options);
    }


    /**
     * Deletes (whether hard or soft) all records that match the given set of column values
     * Returns the number of records deleted.
     *
     * @param string  $deleteString
     * @param Builder $predicate
     * @param array   $columnList
     * @param array   $options
     * @return int
     */
    protected function executeDelete($deleteString, Builder $predicate, array $columnList, array $options = [])
    {
        // as an OH&S measure, the column list MUST NOT be empty (and thus match all records)
        if (empty($columnList) && empty($predicate->getWhere())) {
            return 0;
        }

        $preCallback  = self::CALLBACK_PREDELETE;
        $postCallback = self::CALLBACK_POSTDELETE;
        if (isset($this->soft_delete_column)) {
            $preCallback  = self::CALLBACK_PRESOFTDELETE;
            $postCallback = self::CALLBACK_POSTSOFTDELETE;
            // Don't re-delete already deleted rows
            $columnList[$this->soft_delete_column] = $this->soft_delete_undeleted_value;
        }

        try {
            // Build WHERE part
            $predicate->addColumnList($columnList);
        } catch (UnderflowException $e) {
            // this means an empty IN was provided, so no records can be matched: bail out
            return 0;
        }

        // Call pre-delete hooks
        $deletedItems = $this->findByPredicates($predicate, $options);
        foreach ($deletedItems as $model) {
            if (!$this->triggerCallbacks($preCallback, $model)) {
                return 0;
            }
        }

        // build query
        $query = $deleteString . ' WHERE ' . implode(' AND ', $predicate->getWhere());

        // execute with predicate params
        $statement = $this->getDatabase()->query($query, $predicate->getParams());

        // Call post-delete hooks
        foreach ($deletedItems as $model) {
            $this->triggerCallbacks($postCallback, $model);
        }

        // Raise notifications
        foreach ($deletedItems as $model) {
            $this->raiseNotification($model, self::METHOD_DELETE);
        }

        // return the number of rows deleted
        return $statement->rowCount();
    }


    /**
     * Restores one or more models that were soft deleted.
     *
     * @param object|Collection $object Model or collection of models to restore
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
            self::CALLBACK_POSTRESTORE,
            self::METHOD_CREATE
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
     * @param string $action       The action to send to the notifier, if any.
     *
     * @return bool
     */
    protected function updateAll(
        $object,
        $field,
        $value,
        $pre_trigger = null,
        $post_trigger = null,
        $action = null
    ) {
        $collection = $this->getModelAsCollection($object);
        if (count($collection) === 0) {
            // Nothing to update
            return false;
        }

        // Trigger the pre-trigger, if there is one.
        if (isset($pre_trigger)) {
            foreach ($collection as $model) {
                if (!($this->triggerCallbacks($pre_trigger, $model))) {
                    return false;
                }
            }
        }

        // Get the property to update on each model, if there is one.
        $property_map = null;
        foreach ($this->map as $map) {
            if (isset($map['column']) && $map['column'] === $field) {
                $property_map = $map;
                break;
            }
        }
        if (!isset($property_map)) {
            // Nothing to update
            return false;
        }

        $pbs = $this->buildPredicateBuildersForCollection($collection);

        // Update property on each model
        foreach ($collection as $model) {
            if (isset($property_map)) {
                $this->setModelProperty($model, $property_map['property'], $value);
            }
        }

        // Run update queries
        $this->database->beginTransaction();
        foreach ($pbs as $pb) {
            // Build query

            $query     = "UPDATE `{$this->getTable()}` SET {$this->database->getQuoter()->quoteIdentifier($field)} = :value WHERE " . implode(' AND ', $pb->getWhere());
            $statement = $this->database->prepare($query);
            $statement->bindValue('value', $value);
            foreach ($pb->getParams() as $key => $id) {
                $statement->bindValue($key, $id);
            }

            // Run query
            try {
                if (!$statement->execute()) {
                    $this->database->rollBack();
                    return false;
                }
            } catch (PDOException $e) {
                $this->database->rollBack();
                throw $e;
            }
        }
        $this->database->commit();

        // Trigger the post-trigger, if there is one.
        if (isset($post_trigger)) {
            foreach ($collection as $model) {
                $this->triggerCallbacks($post_trigger, $model);
            }
        }

        // Send the notification action, if there is one.
        if (isset($action)) {
            foreach ($collection as $model) {
                $this->raiseNotification($model, $action);
            }
        }

        return true;
    }


    /**
     * Creates a set of predicate builders - between them, they will match all
     * objects in $collection.
     *
     * This is used in `updateAll` and `hardDelete`.
     *
     * @param Collection $collection A collection of models
     * @return Builder[]
     * @throws UnderflowException
     * @throws InvalidColumnException
     * @throws TypeError
     */
    protected function buildPredicateBuildersForCollection(Collection $collection)
    {
        // Build a WHERE part using the predicate builder.
        /** @var Builder[] $pbs */
        $pbs = [];
        if ($this->hasIdentifier()) {
            // Building the query is easy, just set where any identifier matches
            $idValues = [];
            foreach ($collection as $model) {
                $idValues[] = $this->getModelProperty($model, $this->getIdentifierProperty());
            }
            $pbs[] = $this->getPredicateBuilder()->in($this->getIdentifierColumn(), $idValues);
        } else {
            // Building the query is hard - all I can do is compare against each property of each model.
            foreach ($collection as $i => $model) {
                // Need a separate PB for each model.
                // This is because joining them together doesn't know how to re-alias params.
                $pb = $this->getPredicateBuilder();
                foreach ($this->map as $column) {
                    $property = $this->getModelProperty($model, $column['property']);
                    if (\is_iterable($property)) {
                        $pb->in($column['column'], $property);
                    } elseif (null === $property) {
                        $pb->isNull($column['column']);
                    } else {
                        $pb->equals($column['column'], $property);
                    }
                }
                $pbs[] = $pb;
            }
        }
        return $pbs;
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
     * @inheritDoc
     */
    public function getIdentifier(): ?array
    {
        return $this->identifier;
    }

    /**
     * Retrieves identifier property name.
     *
     * @return string|null
     */
    public function getIdentifierProperty(): ?string
    {
        if (!$this->getIdentifier()) {
            return null;
        }
        return $this->getIdentifier()['property'] ?? null;
    }

    /**
     * Retrieves identifier column name.
     *
     * @return string|null
     */
    public function getIdentifierColumn(): ?string
    {
        if (!$this->getIdentifier()) {
            return null;
        }
        return $this->getIdentifier()['column'] ?? null;
    }

    /**
     * Sets identifier for this mapper.
     *
     * @param array $identifier
     * @return $this
     */
    protected function setIdentifier($identifier)
    {
        $this->identifier = $identifier;
        return $this;
    }

    /**
     * Does this mapper have an identifier? If it doesn't, that makes updates
     * and deletes problematic.
     *
     * @return bool
     */
    public function hasIdentifier()
    {
        return !empty($this->getIdentifier());
    }


    /**
     * @return null|array
     */
    protected function getCreatedMap(): ?array
    {
        return $this->createdMap;
    }

    /**
     * @param null|array $createdMap
     * @return $this
     */
    private function setCreatedMap(?array $createdMap): self
    {
        $this->createdMap = $createdMap;
        return $this;
    }

    /**
     * @return null|array
     */
    protected function getUpdatedMap(): ?array
    {
        return $this->updatedMap;
    }

    /**
     * @param null|array $updatedMap
     * @return $this
     */
    private function setUpdatedMap(?array $updatedMap): self
    {
        $this->updatedMap = $updatedMap;
        return $this;
    }

    /**
     * Enables cache
     *
     * Intentionally not exposed, this should be controlled by `setup()` or
     * `postSetup()`.
     */
    protected function enableCache()
    {
        $this->cacheEnabled = true;
    }

    /**
     * Disables cache
     *
     * Intentionally not exposed, this should be controlled by `setup()` or
     * `postSetup()`.
     */
    protected function disableCache()
    {
        $this->cacheEnabled = false;
    }

    /**
     * Is cache enabled?
     *
     * @return bool
     */
    public function isCacheEnabled()
    {
        return $this->cacheEnabled;
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
     * @return GenericCollection
     */
    public function findAll($options = array())
    {
        return $this->findByColumnList(array(), $options);
    }

    /**
     * Retrieves a model by id
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
        return $this->findOneByColumn($this->getIdentifierColumn(), $identifierValue);
    }

    /**
     * `findById` with fallback to:
     * * soft-deleted version of object
     * * empty object if hard-deleted
     *
     * @param  mixed $identifierValue
     * @return object
     * @throws Exception
     */
    public function findByIdWithFallback($identifierValue)
    {
        try {
            return $this->findById($identifierValue);
        } catch (Mapper\Exception\Deleted $e) {
            return $e->getDeletedObject();
        } catch (Mapper\Exception $e) {
            return $this->createModel();
        }
    }

    /**
     * Returns a collection with models filtered by column/property
     *
     * @param string $columnName
     * @param mixed  $columnValue
     * @param array  $options @see findByColumnList() $options param
     *
     * @return GenericCollection
     */
    public function findByColumn($columnName, $columnValue, $options = array())
    {
        return $this->findByColumnList(array($columnName => $columnValue), $options);
    }

    /**
     * @inheritDoc
     */
    public function findOneByColumn($columnName, $columnValue, array $options = array())
    {
        return $this->findOneByColumnList(array($columnName => $columnValue), $options);
    }

    /**
     * @inheritDoc
     */
    public function findOneByColumnList($columnList, array $options = array())
    {
        $defaults = [
            'deleted' => true,
            'order'   => [],
        ];
        $options = array_merge($defaults, $options);

        $result_collection = $this->findByColumnList(
            $columnList,
            $options
        );
        if ($result_collection->count() === 0) {
            throw new Exception\NotFound();
        }

        // Use the first non-deleted model
        foreach ($result_collection as $model) {
            if ($model instanceof ISoftDeletable && $model->isDeleted()) {
                continue;
            }
            return $model;
        }

        // Were all items deleted?
        // If so, throw a deleted exception with the first item in the list.
        $e = new Exception\Deleted();
        $e->setDeletedObject($result_collection->first());
        throw $e;
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
     * @return GenericCollection
     */
    public function findByColumnList($columnList, $options = array())
    {
        return $this->executeSelect($this->getSelectString(), $this->getFromString(), new Builder($this->getDatabase()->getQuoter(), $this->getTable()), $columnList, $options);
    }

    /**
     * Generates a custom query
     *
     * @param Builder $pb
     * @param array   $options
     * @return GenericCollection
     */
    public function findByPredicates(Builder $pb, array $options = [])
    {
        return $this->executeSelect($this->getSelectString(), $this->getFromString(), $pb, [], $options);
    }


    /**
     * Finds a single model that matches the predicate and options, or throws an exception
     *
     * @param Builder $pb
     * @param array   $options
     * @return IModel
     * @throws Exception\NotFound
     * @throws Exception          If multiple models found that match predicate
     */
    public function findOneByPredicates(Builder $pb, array $options = []): IModel
    {
        $collection = $this->findByPredicates($pb, $options);

        // Nothing found?
        if ($collection->count() === 0) {
            throw new Exception\NotFound();
        }

        // Single model found?
        if ($collection->count() === 1) {
            return $collection->first();
        }

        // Multiple found
        throw new Exception("{$collection->count()} models found that match predicate and options");
    }


    /**
     * Deletes (whether hard or soft) all records that match the given Predicates
     * Returns the number of records deleted.
     *
     * @param Builder $pb
     * @param  array  $options
     * @see $this->findByColumnList()
     * @return int
     */
    public function countByPredicates(Builder $pb, array $options = []): int
    {
        try {
            // None of these things matter when counting
            unset($options['order'], $options['limit'], $options['offset']);
            $stmt = $this->selectScalarByPredicates(
                "COUNT(DISTINCT {$this->table}.{$this->getIdentifierColumn()})",
                $pb,
                $options
            );
            return intval($stmt->fetchColumn());
        } catch (UnderflowException $e) {
            return 0;
        }
    }

    /**
     * Deletes (whether hard or soft) all records that match the given Predicates
     * Returns the number of records deleted.
     *
     * @param Builder $pb
     * @return int
     */
    public function deleteByPredicates(Builder $pb)
    {
        return $this->executeDelete($this->getDeleteString(), $pb, []);
    }

    /**
     * @inheritdoc
     */
    public function getPredicateBuilder()
    {
        return new Builder($this->getDatabase()->getQuoter(), $this->getTable());
    }

    /**
     * @param string  $select
     * @param string  $from
     * @param Builder $predicate
     * @param array   $columnList
     * @param array   $options
     * @return GenericCollection
     */
    protected function executeSelect($select, $from, Builder $predicate, array $columnList, array $options = [])
    {
        try {
            // fold the column list into the predicate builder
            $statement = $this->prepareSelect($select, $from, $predicate, $columnList, $options);

            // Always bypass if within CLI/daemon context.
            // This is because database contents will change before the process dies.
            $bypassCache =
                !$this->isCacheEnabled()
                || (array_key_exists('cache', $options) && $options['cache'] === false);
            if ($bypassCache) {
                // cache bypass is EXPLICITLY defined
                $c = $this->selectCache->fetch([ $statement, true ]);
            } else {
                $params = $predicate->getParams();
                ksort($params);

                // the query itself gets cached by the DB class, but we also want to cache the params
                $hash = sha1(sprintf(
                    '%s[%s][%s]',
                    $statement->queryString,
                    implode(',', $params),
                    \serialize($options)
                ));

                $c = $this->selectCache->get($hash, [ $statement ]);
            }

            $this->monitor->stopExecuteSelectTimer($this, $predicate);

            // Ensure collection objects are distinct while containing references to unique model instances
            return $c instanceof Collection ? $c->shallowClone() : $c;
        } catch (UnderflowException $e) {
            return $this->hydrateList([]);
        }
    }


    /**
     * @param string  $select
     * @param string  $from
     * @param Builder $predicate
     * @param array   $columnList
     * @param array   $options
     * @throws UnderflowException
     * @return PDOStatement
     */
    protected function prepareSelect($select, $from, Builder $predicate, array $columnList, array $options = [])
    {
        $query = "$select $from";

        // Compatibility with old deleted option: if a non-array was passed,
        // assume that we're asking for whether the item was deleted.
        if (!is_array($options)) {
            $options = array('deleted' => $options,);
        }
        $defaults     = array(
            'limit'   => null,
            'offset'  => null,
            'deleted' => false,
            'order'   => $this->getDefaultOrderByParams(),
        );
        $options      = array_merge($defaults, $options);
        $quoted_table = $this->database->getQuoter()->quoteIdentifier($this->table);

        // filter on soft-deletions if this mapper supports them
        if (isset($this->soft_delete_column) && $options['deleted'] == false) {
            if (null === $this->soft_delete_undeleted_value) {
                $predicate->isNull($this->soft_delete_column);
            } else {
                $predicate->equals($this->soft_delete_column, $this->soft_delete_undeleted_value);
            }
        }

        // If this underflows, someone passed an empty column value - return an empty set.
        $this->buildWherePart($predicate, $columnList, $options);

        $this->monitor->assessPredicate($predicate, $query);

        // capture this regardless of emptiness
        $params = $predicate->getParams();

        // only actually add a where clause if there are predicates to test
        if ($predicate->count()) {
            $query .= ' WHERE ' . $this->getPredicateBuilder()->all($predicate)->getWhere()[0];
        }

        // Build ORDER BY part
        if (isset($options['order']) && \is_array($options['order']) && count($options['order'])) {
            $query .= self::buildOrderByClause($quoted_table, $options['order'], $this->database->getQuoter());
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

        $this->monitor->startExecuteSelectTimer($this, $predicate);

        $statement = $this->database->prepare($query);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        // Bind limit and offset if they were set
        if (isset($limit)) {
            $limit = (int)$limit;
            $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        }
        if (isset($offset)) {
            $offset = (int)$offset;
            $statement->bindValue('offset', $offset, \PDO::PARAM_INT);
        }

        return $statement;
    }

    /**
     * Returns the default order by clause (if any), this mapper should apply to queries.
     * Any 'order' option passed to mapper methods will override (not merge with) this default.
     *
     * Eg,
     *  If this mapper's default is ['sequence' => 'ASC] and we call $mapper->findAll(['order' => ['name' => 'ASC']])
     *  Then final order by will be ['name' => 'ASC'], rather than ['name' => 'ASC', 'sequence' => 'ASC]
     *
     * @return array
     */
    protected function getDefaultOrderByParams(): array
    {
        return [];
    }

    /**
     * Returns an 'ORDER BY' clause
     *
     * @param string   $quoted_table
     * @param string[] $order            Map of columnName to orderOption, eg ['id' => 'ASC]
     * @param IQuoter  $db
     * @return string
     */
    public static function buildOrderByClause(string $quoted_table, array $order, IQuoter $db): string
    {
        // Build ORDER part (in an injection-safe way)
        $order_parts = array();
        if (!empty($order)) {
            foreach ($order as $column => $order_option) {
                // Is there a . in escaped_name? Assume it's a sort on a join
                $parts = \explode('.', $column, 2);
                if (count($parts) === 1) {
                    $prefix     = $quoted_table;
                    $columnName = $db->quoteIdentifier($column);
                } else {
                    $prefix     = $db->quoteIdentifier($parts[0]);
                    $columnName = $db->quoteIdentifier($parts[1]);
                }

                $order_option = \strtoupper($order_option);
                if (\in_array($order_option, array('ASC', 'DESC'))) {
                    $order_parts[] = "$prefix.$columnName $order_option";
                }
            }
        }
        return empty($order_parts) ? '' : ' ORDER BY ' . \implode(', ', $order_parts);
    }


    /**
     * Return the first part of the string required to delete from this mapper
     *
     * @return string
     */
    protected function getDeleteString()
    {
        $quoted_table = $this->database->getQuoter()->quoteIdentifier($this->table);

        // only soft-delete if this is a soft-deletable model
        if ($softDelete = $this->getSoftDeleteUpdateSql()) {
            return "UPDATE $quoted_table SET $softDelete";
        }

        return "DELETE FROM $quoted_table";
    }

    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString()
    {
        // in the core mapper this is quite trivial, but implementing classes can override
        $quoted_table = $this->database->getQuoter()->quoteIdentifier($this->table);
        return "SELECT {$this->getColumnString()} FROM $quoted_table";
    }


    /**
     * It seems we're going down this road...
     * To prevent cataclysmic backward-incompatibility, in the core, the FROM is absorbed into the SELECT... for now...
     *
     * @return string
     */
    protected function getFromString()
    {
        return "";
    }


    /**
     * @param PDOStatement $statement
     * @return GenericCollection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        // in the core mapper this is quite trivial, but implementing classes can override
        return $this->hydrateList($this->iterateStatement($statement));
    }


    /**
     * This method exists as a shim to make tests work, because testing PDO is freaking hard... -_-'
     *
     * @param PDOStatement $stmt
     * @return Traversable
     */
    private function iterateStatement(PDOStatement $stmt)
    {
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $r;
        }
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
            // None of these things matter when counting
            unset($options['order'], $options['limit'], $options['offset']);
            $stmt = $this->selectScalarByColumnList(
                'COUNT(*)',
                $columnList,
                $options,
                $this->isJoinRequired(null, $columnList, $options)
            );
            return (int)$stmt->fetchColumn();
        } catch (UnderflowException $e) {
            return 0;
        }
    }


    /**
     * Verifies that a field's value is unique. (Currently ignores soft-deleted rows.)
     *
     * @param  array  $columnList The column list to check for uniqueness.
     *                            (@see `findByColumnList`)
     * @param  mixed  $currentId  The ID of the row I'm trying to save.
     *                            It's okay if the row I'm trying to save has
     *                            this value already.
     *
     * @return bool
     */
    public function isUnique($columnList, $currentId)
    {
        $query = $this->selectScalarByColumnList(
            $this->getTable() . '.' . $this->getIdentifierColumn(),
            $columnList,
            [],
            $this->isJoinRequired(null, $columnList, [])
        );
        $query->execute();
        while (($dupeId = $query->fetchColumn()) !== false) {
            if ($currentId == $dupeId) {
                // I'm trying to save this row. I don't mind if it dupes itself
                continue;
            } else {
                // This is a legitimate dupe
                return false;
            }
        }
        // No duplicates
        return true;
    }

    /**
     * Returns a statement that attempts to select the scalar given by $selection
     *
     * @param  string $selection
     * @param  array  $columnList
     * @param  array  $options
     * @param  bool   $useJoins   If true: use any joins from `getFromString`.
     *                            Otherwise, query only this mapper's table:
     *                            this is faster, but may miss information.
     *
     * @throws UnderflowException YOU MUST CATCH THIS. Thrown if the query could not possibly return any results.
     * @return PDOStatement
     */
    protected function selectScalarByColumnList(
        $selection,
        array $columnList,
        array $options = [],
        bool $useJoins = true
    ) {
        $select = "SELECT $selection";

        // Only apply the default order by if there's a limit or offset option.
        // This is because ordering a result is likely to create a temp table.
        if (!isset($options['limit']) && !isset($options['offset'])) {
            $options['order'] = $options['order'] ?? [];
        }

        // grab a from string if using joins.
        // Fall back to just selecting from this table.
        $from = '';
        if ($useJoins) {
            $from = $this->getFromString();
        }
        if ($from === '') {
            $from = "FROM {$this->getTable()}";
        }

        $statement = $this->prepareSelect($select, $from, new Builder($this->getDatabase()->getQuoter(), $this->getTable()), $columnList, $options);

        $statement->execute();

        return $statement;
    }

    /**
     * Returns a statement that attempts to select the scalar given by $selection
     *
     * @param  string  $selection
     * @param  Builder $pb
     * @param  array   $options
     *
     * @throws UnderflowException YOU MUST CATCH THIS. Thrown if the query could not possibly return any results.
     * @return PDOStatement
     */
    protected function selectScalarByPredicates($selection, Builder $pb, array $options = [])
    {
        $select = "SELECT $selection";

        // grab a from string if it hasn't been implemented yet
        // I cannot tell from a predicate builder if it touches tables outside
        // this mapper's table, so I must include joins if present.
        if (!($from = $this->getFromString())) {
            $from = "FROM {$this->getTable()}";
        }
        $statement = $this->prepareSelect($select, $from, $pb, [], $options);
        $statement->execute();
        return $statement;
    }


    /**
     * Does this column list and set of options require joining onto any other
     * table?
     *
     * If any of the columns in the selection, WHERE or ORDER BY are in a joined
     * table, then a join is required.
     *
     * @param string|null $selection
     * @param array       $columnList
     * @param array       $options
     * @return bool
     */
    private function isJoinRequired(?string $selection, array $columnList, array $options): bool
    {
        if ($this->isJoinRequiredOnColumn($selection)) {
            return true;
        }
        foreach ($columnList as $column => $value) {
            if ($this->isJoinRequiredOnColumn($column)) {
                return true;
            }
        }
        if (is_iterable($options['order'] ?? null)) {
            foreach ($options['order'] as $column => $value) {
                if ($this->isJoinRequiredOnColumn($column)) {
                    return true;
                }
            }
        }
        return false;
    }


    /**
     * Is a join required on this column?
     *
     * @param mixed $column Anything could be passed in an order options array,
     *                      so account for anything.
     * @return bool
     */
    private function isJoinRequiredOnColumn($column): bool
    {
        return is_string($column) && strpos($column, '.') !== false;
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
    public function findIdsByColumnList(array $columnList, array $options = []): array
    {
        try {
            $stmt = $this->selectScalarByColumnList(
                'DISTINCT ' . $this->getTable() . '.' . $this->getIdentifierColumn(),
                $columnList,
                $options,
                $this->isJoinRequired(null, $columnList, $options)
            );
        } catch (UnderflowException $e) {
            return [];
        }
        $ids        = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idHydrator = $this->getColumnScalarHydrator($this->getIdentifierColumn());
        return array_map([$idHydrator, 'hydrate'], $ids);
    }


    /**
     * @param Builder $pb
     * @param array            $options
     * @return int[]
     */
    public function findIdsByPredicates(Builder $pb, array $options = [])
    {
        return $this->findScalarByPredicates($this->getIdentifierColumn(), $pb, $options);
    }


    /**
     * @param string  $column
     * @param Builder $pb
     * @param array   $options
     * @return array
     */
    public function findScalarByPredicates(string $column, Builder $pb, array $options = []): array
    {
        try {
            $scalars = $this->selectColumnsByPredicates([$column], $pb, $options)->fetchAll(PDO::FETCH_COLUMN);
        } catch (UnderflowException $e) {
            return [];
        }
        if (count($scalars)) {
            $hydrator = $this->getColumnScalarHydrator($column);
            $scalars  = array_map([$hydrator, 'hydrate'], $scalars);
        }
        return $scalars;
    }


    /**
     * @param string[] $columnList
     * @param Builder  $pb
     * @param array    $options
     * @return array
     */
    public function findColumnsByPredicates($columnList, Builder $pb, array $options = []): array
    {
        try {
            $rows = $this->selectColumnsByPredicates($columnList, $pb, $options)->fetchAll(PDO::FETCH_ASSOC);
        } catch (UnderflowException $e) {
            return [];
        }
        if (count($rows)) {
            /** @var IColumnType[] $hydrators */
            $hydrators = [];
            foreach ($columnList as $column) {
                $hydrators[$column] = $this->getColumnScalarHydrator($column);
            }
            foreach ($rows as $rowId => $row) {
                foreach ($row as $column => $value) {
                    $rows[$rowId][$column] = $hydrators[$column]->hydrate($value);
                }
            }
        }
        return $rows;
    }


    /**
     * @param string[] $columnList
     * @param Builder  $pb
     * @param array    $options
     * @return PDOStatement
     * @throws UnderflowException
     */
    private function selectColumnsByPredicates($columnList, Builder $pb, array $options = [])
    {
        $selection = implode(', ', array_map(function ($column) {
            return implode('.', array_map(function ($v) {
                return $this->database->getQuoter()->quoteIdentifier($v);
            }, [ $this->getTable(), $column ]));
        }, $columnList));

        return $this->selectScalarByPredicates($selection, $pb, $options);
    }


    /**
     * Builds a where part and params for this query out of a column list
     *
     * @param Builder $predicate
     * @param  array  $columnList
     * @param  array  $options
     *
     * @return array where:  The where parts of the query
     *               params: the parameters
     * @throws \UnderflowException
     */
    protected function buildWherePart(Builder $predicate, $columnList, $options)
    {
        return $predicate->addColumnList($columnList)->getPredicates();
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
     * @param object $object
     *
     * @return int[] IDs of newly-created models
     */
    public function create($object)
    {
        $collection = $this->getModelAsCollection($object);
        if ($this->hasIdentifier()) {
            foreach ($collection as $model) {
                $this->setModelProperty($model, $this->getIdentifierProperty(), null);
            }
        }
        return $this->save($collection);
    }


    /**
     * @brief Saves an object to the database. Updates or inserts
     *
     * @param object $object
     * @return bool|int[]
     * @throws Exception
     * @throws PDOException If unable to execute SQL
     * @throws UnexpectedValueException If a triggered callback failed, and caller should know about it
     */
    public function save($object)
    {
        $collection  = $this->getModelAsCollection($object);
        $tbl         = $this->table;
        $colString   = $this->getColumnString();
        $collection  = $this->populateUuids($this->populateSaveTimestamps($collection));
        $plcString   = $this->getPlaceholderString();
        $updString   = $this->getUpdateString();
        $affectedIds = [];
        $pdoMethods  = [];

        $idProperty       = $this->getIdentifierProperty();
        $idColumn         = $this->getIdentifierColumn();
        $insertQuery      = "INSERT INTO `{$tbl}` ({$colString}) VALUES ({$plcString})";
        $insertStatement  = null;
        $existingObjects  = [];

        if (isset($idColumn)) {
            $insertStatement = $this->database->prepare($insertQuery);
            // retrieve the objects as they currently exist so we can perform a diff if we need to
            // we first have to dehydrate to ensure we don't attempt queries with objects as parameters
            $collectionIds = array_filter(
                $collection->map(
                    function ($model) {
                        return $this->dehydrate($model)[$this->getIdentifierColumn()];
                    }
                )->getAll()
            );

            if (count($collectionIds)) {
                foreach (
                    $this->findByColumn($idColumn, $collectionIds, [
                        'deleted' => true,
                        'cache'   => false,
                    ]) as $obj
                ) {
                    // index by ID
                    $existingObjects[$this->getModelProperty($obj, $idProperty)] = $obj;
                }
            }
        }

        //
        // Trigger all on collection before attempting to run?
        // Must for now, because otherwise transactions would be nested.
        foreach ($collection as $model) {
            $previous = null;
            $id       = null;
            if ($idProperty) {
                // this mapper defines a primary key, so providing a diff is possible (if this is an UPDATE)
                $id = $this->getModelProperty($model, $idProperty);
                if ($id) {
                    $previous = array_key_exists($id, $existingObjects) ? $existingObjects[$id] : null;
                }
            }

            if (!$this->triggerCallbacks(self::CALLBACK_PRESAVE, $model, $previous)) {
                return false;
            }
        }

        foreach ($collection as $model) {
            $this->assertValidEnums($model);
        }

        $replaceQuery     = "{$insertQuery} ON DUPLICATE KEY UPDATE {$updString}";
        $replaceStatement = $this->database->prepare($replaceQuery);

        $this->database->beginTransaction();

        try {
            foreach ($collection as $i => $model) {
                // Use the insert if the record has no ID: otherwise the replace
                // (Need to use replace rather than update to handle tables with
                //  a non-auto-incrementing primary key, i.e. storage.hash)
                if (isset($insertStatement) && !$this->getModelProperty($model, $idProperty)) {
                    $result         = $insertStatement->execute($this->dehydrate($model));
                    $pdoMethods[$i] = self::METHOD_CREATE;
                } else {
                    $result = $replaceStatement->execute($this->dehydrate($model));
                    // take note of whether this was an insert or update
                    switch ($replaceStatement->rowCount()) {
                        case self::PDO_RESULT_INSERT:
                            $pdoMethods[$i] = self::METHOD_CREATE;
                            break;
                        case self::PDO_RESULT_NOCHANGE:
                        case self::PDO_RESULT_UPDATE:
                            $pdoMethods[$i] = self::METHOD_UPDATE;
                            break;
                        default:
                            $pdoMethods[$i] = null;
                            break;
                    }
                }

                if ($result) {
                    if ($idProperty) {
                        if (!$this->getModelProperty($model, $idProperty)) {
                            $new_id = $this->database->lastInsertId();
                            $this->setModelProperty($model, $idProperty, $new_id);
                        }
                        $affectedIds[] = $this->getModelProperty($model, $idProperty);
                    }

                    if (!$this->savePolyJoins($model)) {
                        $this->database->rollBack();
                        return false;
                    }
                } else {
                    $this->database->rollBack();
                    return false;
                }
            }
        } catch (PDOException $e) {
            $this->database->rollBack();
            throw $e;
        }
        $this->database->commit();
        foreach ($collection as $i => $model) {
            $previous = null;

            if ($idProperty) {
                // this mapper defines a primary key, so providing a diff is possible (if this is an UPDATE)
                $id = $this->getModelProperty($model, $idProperty);

                if ($id) {
                    $previous = array_key_exists($id, $existingObjects) ? $existingObjects[$id] : null;
                    $this->triggerCallbacks(self::CALLBACK_POSTSAVE, $model, $previous);
                }
            } else {
                // this mapper does not define primary key: we cannot supply a diff, but we *can* still call the POSTSAVE hook
                $this->triggerCallbacks(self::CALLBACK_POSTSAVE, $model);
            }

            $this->raiseNotification($model, $pdoMethods[$i], $previous);
        }
        return $affectedIds;
    }

    // Despite PHPStorm's warning, the PDOException is thrown during calls to PDO::execute() for SQL errors because
    // we have configured the PDO connection with $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    /** @noinspection PhpDocRedundantThrowsInspection */
    /**
     * Update any n:n relations that exist
     *
     * @param object $model
     * @throws PDOException If unable to execute SQL. (as PDO is configured with PDO::ERRMODE_EXCEPTION)
     * @return bool
     */
    private function savePolyJoins($model)
    {
        $deletePattern = 'DELETE FROM `%s` WHERE `%s` = :this_key';
        $insertPattern = 'INSERT IGNORE INTO `%s` (`%s`, `%s`) VALUES (:this_key, :other_key)';

        try {
            foreach ($this->getMap() as $map) {
                if (!$this->polyJoinExists($map)) {
                    // not interested
                    continue;
                }

                // We use join tables for two use cases:
                // 1. True N:N relationships (eg folder:yearLevel), and
                // 2. N:0..1 relationships (eg assessment:unitSpec)
                // The first case is a Collection, the latter a Model.
                // For N:N, null means 'unknown', while empty means 'no associations'.
                // For N:0..1, null could either mean 'unknown' or 'no association'.
                // For the latter, for now assume the join is always hydrated (see SA-9156)
                $dehydrated = $this->getModelProperty($model, $map['property']);
                if (null === $dehydrated && $map['hydrator'] instanceof Column\Collection) {
                    // Join is not hydrated on this model
                    continue;
                }
                $propertyValue = (array)$dehydrated;

                $joinSchema = $this->joins[$map['join']];
                $joinMulti  = $joinSchema['multi'];
                $thisKey    = $this->getModelProperty($model, $this->getPropertyFromColumn($joinSchema['this_key']));

                $viaTable   = $joinMulti['via_table'];
                $viaLocal   = $joinMulti['this_key'];
                $viaForeign = $joinMulti['other_key'];

                // step 1: delete existing relations in join table
                $deleteRelationStatement = $this->database->prepare(sprintf($deletePattern, $viaTable, $viaLocal));

                $deleteRelationStatement->bindValue(':this_key', $thisKey);

                if (!$deleteRelationStatement->execute()) {
                    return false;
                }

                // step 2: insert new relations in join table
                $insertRelationStatement = $this->database->prepare(sprintf($insertPattern, $viaTable, $viaLocal, $viaForeign));

                // iterate the values given and insert into join table
                $otherMapper       = $this->getJoinMapper($map['join']);
                $otherModelExample = $otherMapper->createModel();

                foreach ($propertyValue as $value) {
                    $otherKey = $value;

                    // Is $otherKey a model? If so I need its identifier.
                    if ($value instanceof $otherModelExample) {
                        $otherKey = $otherMapper->getModelProperty($value, $this->getIdentifierProperty());
                    }

                    $success = $insertRelationStatement->execute([
                        'this_key'  => $thisKey,
                        'other_key' => $otherKey
                    ]);

                    if (!$success) {
                        return false;
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
            // in all exceptional cases except PDOException, we return false
            return false;
        } catch (Exception $e) {
            return false;
        } catch (Mapper\DefinitionException $e) {
            return false;
        }

        // even if we did nothing, nothing is still a success
        return true;
    }


    /**
     * Does this specific mapping have a n:n join?
     *
     * @param array $map
     * @return bool
     */
    private function polyJoinExists(array $map)
    {
        return !(
            empty($map['join'])
            || empty($this->joins[$map['join']]['multi'])
            || (isset($this->joins[$map['join']]['multi']['read_only']) && $this->joins[$map['join']]['multi']['read_only'])
        );
    }

    /**
     * Setup method. Implementing classes to define their SQL map here.
     * @return void
     */
    abstract protected function mapping();


    /**
     * Registers a property as being an ENUM.
     *
     * This will prevent the mapper from saving a model with a property that is not a valid enum
     *
     * @param string $propertyName
     * @param array  $permittedValues
     * @throws InvalidArgumentException
     */
    protected function registerEnum(string $propertyName, array $permittedValues): void
    {
        if (\array_key_exists($propertyName, $this->enums)) {
            throw new InvalidArgumentException("'$propertyName' is already registered as an enum");
        }
        $this->enums[$propertyName] = $permittedValues;
    }


    /**
     * Asserts all registered enums are valid
     *
     * @param object $model
     * @throws UnexpectedValueException
     */
    protected function assertValidEnums($model): void
    {
        foreach ($this->enums as $propertyName => $permittedValues) {
            $propertyValue = $this->getModelProperty($model, $propertyName);
            if (\in_array($propertyValue, $permittedValues, true)) {
                continue;
            }
            throw new UnexpectedValueException("$propertyName has invalid enum value: '$propertyValue'");
        }
    }


    protected function hasTable($table)
    {
        if (!is_string($table)) {
            return false;
        }
        $this->table = $table;
        return true;
    }


    /**
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

    public function hasModuleNotifier($classname)
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
     * @throws Mapper\DefinitionException If join $name isn't on this mapper
     */
    public function getJoinMapper($name)
    {
        return $this->joinMapperCache->get($name, [ $name ]);
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
            if ($filterSoftDeleteSql !== '') {
                $join_sql .= ' AND ' . $filterSoftDeleteSql;
            }
        }
        if (isset($join['extra']) && strlen($join['extra']) > 0) {
            $join_sql .= ' AND ' . $join['extra'];
        }
        return strtr($join_sql, $params);
    }

    /**
     * @deprecated in favour of direct overriding of static::hydrateSelect()
     *
     * Hydrates a row from the database into a model.
     *
     * @param Traversable|array $rowList  The rows from the database.
     * @param string            $alias    The prefix of this mapper's table cells in $row
     * @param callable          $callback A callback to apply on all models (@see $this->hydrate)
     *
     * @return GenericCollection
     */
    public function hydrateList($rowList, $alias = '', $callback = null)
    {
        $modelList = $this->createCollection();
        foreach ($rowList as $row) {
            $model = $this->hydrate($row, $alias, $callback);
            if ($model) {
                $modelList->add($model);
            }
        }
        return $modelList;
    }

    /**
     * Hydrates a row from the database into a model.
     *
     * @param array         $row      The row from the database.
     * @param string        $alias    The prefix of this mapper's table cells in $row
     * @param callable|null $callback If supplied and callable, this method is
     *                                applied to the model once it's retrieved.
     *                                It takes the following parameters:
     *                                $mapper: this object
     *                                $model:  the model that has been hydrated
     *                                $row:    the row from the database
     *
     * @throws Exception
     * @return null|object
     */
    public function hydrate(array $row, $alias = '', $callback = null)
    {

        // Validate fields if this is in debug mode.
        if ($this->getConfigLoader()->get('debug_mode')) {
            // $alias is bad if there are NO fields in $row that match it
            // (Consider the amount of overhead this check may add)
            if (!empty($alias)) {
                // Search for rows where columnName matches this alias
                $aliasFound = false;
                $prefix     = $alias . '.';
                foreach ($row as $columnName => $value) {
                    if (strpos($columnName, $prefix) === 0) {
                        $aliasFound = true;
                        break;
                    }
                }

                if (!$aliasFound) {
                    throw new Exception(sprintf('Alias "%s" not found in row - are you hydrating the correct alias?', $alias));
                }
            } else {
                // Search for rows without an alias
                $aliasFound = false;
                foreach ($row as $columnName => $value) {
                    // Check if columnName has no alias
                    if (strpos($columnName, '.') === false) {
                        $aliasFound = true;
                        break;
                    }
                }

                if (!$aliasFound) {
                    throw new Exception('Column without alias not found in row - did you forget to specify the correct alias?');
                }
            }
        }

        return $this->performHydration($this->localBodyCache, $this, $row, $alias, $callback);
    }

    /**
     * Invalidates the hydrate cache for this row and alias.
     *
     * @param array  $row
     * @param string $alias
     */
    public function invalidateHydrateCache(array $row, $alias = '')
    {
        $this->localBodyCache->invalidate($this->getLocalObjectCacheKey($this, $row, $alias));
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
     * @return object|null
     */
    public function hydrateJoin($row, $join, $alias = '', $callback = null)
    {
        $foreignMapper = $this->getJoinMapper($join);

        return $this->performHydration($this->foreignBodyCache, $foreignMapper, $row, $alias, $callback);
    }


    /**
     * Actually performs hydration via the cache given
     *
     * @param Cache    $cache
     * @param Mapper   $mapper
     * @param array    $row
     * @param string   $alias
     * @param callable $callback
     * @return object|null
     */
    private function performHydration(Cache $cache, Mapper $mapper, array $row, $alias = '', $callback = null)
    {
        // courtesy of late static bindings, this private var is accessible even if you're in a different mapper
        // however, it is not accessible to subclasses
        // Always bypass the cache if within CLI/daemon context.
        // This is because database contents will change before the process dies.
        if (!$mapper->isCacheEnabled() || $mapper->localBodyCacheBypass) {
            // fetch direct
            $model = $cache->fetch([ $mapper, $row, $alias ]);
        } else {
            // use cached value if available
            $model = $cache->get($this->getLocalObjectCacheKey($mapper, $row, $alias), [ $mapper, $row, $alias ]);
        }

        // we don't cache this part
        if (is_callable($callback)) {
            $model = call_user_func($callback, $mapper, $model, $row);
        }

        return $model;
    }


    /**
     * @param Mapper $mapper
     * @param array  $row
     * @param string $alias
     * @return string
     */
    private function getLocalObjectCacheKey(Mapper $mapper, array $row, $alias = '')
    {
        $idCol = trim($mapper->getIdentifierColumn());

        if (empty($idCol)) {
            // this occurs on things that fundamentally are join tables, so lack a single primary key
            // unfortunately not much we can do but use the whole row as the cache key
            $key = json_encode($row);
        } else {
            $idKey = trim("$alias.$idCol", '.');

            if (array_key_exists($idKey, $row)) {
                $key = $row[$idKey];
            } else {
                // this is non-fatal; use the whole role as the cache-key (inherently meaning "no cache")
                $key = json_encode($row);

                // we still want to know in development, though.
                $this->monitor->error("Key '$idKey' not defined in row$key; cannot obtain cache key");
            }
        }

        return $this->getLocalKeyString($mapper, $key);
    }

    /**
     * Provides the key string used to store values in the cache
     * @param object $mapper
     * @param string|int $key
     * @return string
     */
    protected function getLocalKeyString($mapper, $key): string
    {
        return get_class($mapper) . ".$key";
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
     * @param  mixed  $value
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
                return "$column = FALSE";
            }
            if ($value === true) {
                return "$column = TRUE";
            }
            return "$column = " . $this->database->quote($value);
        }
        return '';
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
        if ($idValue) {
            // build/retrieve from the column definition (which includes caching)
            return $this->getColumnFactory()->build(static::TYPE_MODEL)->hydrate($idValue, $this);
        }

        if (empty($this->model)) {
            // no model is defined; explode
            throw new Exception('Model is undefined, cannot instantiate.');
        }

        if (!class_exists($this->model)) {
            throw new Exception("Model class does not exist: '{$this->model}'");
        }

        // create a new, blank model
        return new $this->model();
    }

    /**
     * Creates a model from the given join.
     *
     * @param string $join
     * @param int    $idValue
     * @return object
     */
    public function createJoinModel($join, $idValue = null)
    {
        return $this->getJoinMapper($join)->createModel($idValue);
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
            if (empty($map['column']) || isset($map['join_def']['reverse'])) {
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
            if (empty($map['column']) || isset($map['join_def']['reverse'])) {
                continue;
            }
            $placeholderList[] = ":{$map['column']}";
        }
        return implode(', ', $placeholderList);
    }

    /**
     * Gets a string of placeholders for an array - usually used for creating a
     * list of placeholders for an IN condition.
     * @deprecated this has been moved to Mapper\PredicateBuilder
     *
     * @param array  $input
     * @param string $prefix If not supplied, ? will be used as the placeholder
     *                       for all values.
     *
     * @return string Something like "(:id_0, :id_1, :id_2)".
     */
    public function getArrayPlaceholders($input, $prefix = null)
    {
        return Builder::getArrayPlaceholders($input, $prefix);
    }

    /**
     * Flattens a list of parameters for an array.
     *
     * Used in conjunction with getArrayPlaceholders() - calling this with the
     * same input and prefix will generate params and placeholders that work
     * with each other.
     * @deprecated this has been moved to Mapper\PredicateBuilder
     *
     * @param array  $input
     * @param string $prefix If not supplied, ? will be assumed as placeholder
     *                       for all values.
     *
     * @return array
     */
    public function getArrayParameters($input, $prefix = null)
    {
        return Builder::getArrayParameters($input, $prefix);
    }

    /**
     * @brief Returns an SQL fragment containing the assignment portion of an UPDATE query
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
            $assignmentList[] = "`{$this->table}`.`{$map['column']}` = :{$map['column']}";
        }
        return implode(', ', $assignmentList);
    }

    /**
     * Inverse of hydrate(). Converts a model to an array with indices matching the db fields.
     *
     * @param object $model              The model to get values from
     * @throws Mapper\Exception if the map is not valid
     * @return array
     */
    public function dehydrate($model)
    {
        $result = array();
        foreach ($this->map as $map) {
            // If there's a join on this property, we need to know about it
            $join = null;
            if (isset($map['join'])) {
                $join = $this->getJoinDefinition($map['join']);
            }

            // Reverse joins will never dehydrate to a value,
            // because they always join onto the ID field of this mapper's table
            // (which has already been mapped).
            if (!empty($join['reverse'])) {
                continue;
            }

            if (empty($map['column'])) {
                // if column is empty, ensure a join is specified, and that it is a multijoin
                if (isset($join['multi'])) {
                    continue;
                }
                // if the join doesn't exist, or not multijoin, failhard
                throw new Exception("Column must be declared if no multijoin set for it: " . json_encode($map));
            }
            $propertyName = $map['property'];
            $columnName   = $map['column'];
            $result[$columnName] = $this->getModelProperty($model, $propertyName);
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


    /**
     * If callback fails and you want it to be silent, return false.
     * If caller should know why it failed, throw an UnexpectedValueException explaining failure.
     *
     * @param string $point  One of the self::CALLBACK_* constants
     * @return bool
     * @throws \UnexpectedValueException If callback failed and caller should know why
     */
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
     * @throws Exception
     * @return bool
     */
    protected function raiseNotification($object, $verb, $previous = null)
    {
        $sessionUser = $this->getSessionUser();
        if (!$sessionUser) {
            return false;
        }

        $diff = $previous && ($verb != self::METHOD_DELETE) ? $this->diff($object, $previous) : null;

        // create a new event, and send it to the scheduler
        try {
            $this->database->onPersist(function () use ($sessionUser, $verb, $object, $diff) {
                $this->getEventPropagator()->propagate($sessionUser, $verb, $object, $this, $diff);
            });
        } catch (\ErrorException $e) {
            //Unable to connect to notification system
            return false;
        }

        return true;
    }

    /**
     * Set MockPropagator to suppress notification
     * @return $this
     */
    public function suppressNotification(): self
    {
        $this->propagatorSuppressed = $this->getEventPropagator();
        $this->propagator           = new MockPropagator();
        return $this;
    }

    /**
     * Restore notification (Must call suppressNotification first)
     * @return $this
     */
    public function restoreNotification(): self
    {
        if (!isset($this->propagatorSuppressed)) {
            return $this;
        }
        $this->propagator = $this->propagatorSuppressed;
        unset($this->propagatorSuppressed);
        return $this;
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
     * @param array  $keys
     * @return array
     * @throws Exception
     */
    public function diff($objectA, $objectB = null, $keys = [0, 1])
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
            $objectB = $this->findById($this->getModelProperty($objectA, $this->getIdentifierProperty()));
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
                $diff[$property] = array_combine($keys, array($dhA, $dhB));
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
     * "Truncate"
     *
     * This is actually a DELETE FROM instead of a TRUNCATE, because the latter
     * causes an implicit commit.
     *
     * Users will generally want to be able to roll back a mass deletion.
     */
    public function truncate()
    {
        $query     = "DELETE FROM `{$this->table}`";
        $statement = $this->database->prepare($query);
        $statement->execute();
    }


    /**
     * @throws Exception
     * @return array
     */
    public function listUnmappedFields()
    {
        $map   = $this->map;
        $joins = $this->joins;

        $mappedFields = [];

        foreach ($map as $field) {
            if (isset($field['column'])) {
                $mappedFields[$field['column']] = true;
            } elseif (isset($field['join']) && isset($joins[$field['join']])) {
                $mappedFields[$joins[$field['join']]['this_key']] = true;
            } elseif (isset($field['join_def'])) {
                $mappedFields[$field['join_def']['this_key']] = true;
            }
        }

        foreach ($joins as $join) {
            if (isset($join['this_key'])) {
                $mappedFields[$join['this_key']] = true;
            }
        }

        $mappedFields = array_keys($mappedFields);

        $db = $this->getDatabase();

        if (!$db instanceof \Database) {
            throw new Exception("No db in " . get_class($this));
        }

        $stmt = $db->query("SHOW COLUMNS FROM {$this->table}");

        $knownFields = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_diff($knownFields, $mappedFields));
    }


    /**
     * Returns a 1-dimensional array of distinct values in specified column
     * Does not support multi-column distinctness
     *
     * @param string $column                     distinct column
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
     * @return array
     */
    public function findDistinct($column, $columnList = [], $options = []): array
    {
        try {
            $stmt = $this->selectScalarByColumnList(
                "DISTINCT $column",
                $columnList,
                $options,
                $this->isJoinRequired($column, $columnList, $options)
            );
        } catch (UnderflowException $e) {
            return [];
        }
        $distinct = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($distinct)) {
            $hydrator = $this->getColumnScalarHydrator($column);
            $distinct = array_map([$hydrator, 'hydrate'], $distinct);
        }
        return $distinct;
    }

    /**
     * Perform an intersection of the dependent join given against what exists in the database:
     *  - any records that exist in memory are to be persisted to the DB;
     *  - any records that exist in DB but not memory are to be deleted.
     *
     * @param string     $joinName   the join to cascade changes onto
     * @param array      $search     which DB records to pull for comparison
     * @param Collection $collection the new collection to align DB to
     * @return bool
     */
    protected function cascadeJoin($joinName, array $search, Collection $collection)
    {
        $foreignMapper = $this->getJoinMapper($joinName);

        $foreignIdProperty = $foreignMapper->getIdentifierProperty();

        // retrieve records that already exist for this match and index by ID
        $existing = $foreignMapper->findByColumnList($search)->indexBy($foreignIdProperty);
        $create   = $foreignMapper->createCollection($collection->getAll())->filterBy($foreignIdProperty, null);
        $updated  = $foreignMapper->createCollection($collection->getAll())->indexBy($foreignIdProperty);

        unset($updated[null]);

        // extract IDs
        $existingIds = $existing->getColumnValues($foreignIdProperty);
        $updatedIds  = $updated->getColumnValues($foreignIdProperty);

        // determine what to do
        $delete = array_diff($existingIds, $updatedIds);
        $update = array_diff($updatedIds, $delete);

        foreach ($delete as $id) {
            $foreignMapper->delete($existing[$id]);
        }

        foreach ($update as $id) {
            $foreignMapper->save($updated[$id]);
        }

        $foreignMapper->save($create);

        return true;
    }


    /**
     * Set model 'createAt' and update 'updatedAt' timestamps prior to saving to DB
     *
     * @param Collection $collection
     * @return Collection
     */
    protected function populateSaveTimestamps(Collection $collection): Collection
    {
        // Does the mapper have 'created_at' or 'updated_at' timestamps?
        if (!$this->createdMap && !$this->updatedMap) {
            return $collection;
        }
        $now = Alaress_Date_Format::now();

        if ($this->createdMap) {
            $getMethod = $this->getGetMethodName($this->createdMap['property']);
            $setMethod = $this->getSetMethodName($this->createdMap['property']);
            foreach ($collection as $model) {
                // Has the model not been created?
                if (null === $model->{$getMethod}()) {
                    $model->{$setMethod}(clone $now);
                }
            }
        }
        if ($this->updatedMap) {
            $setMethod = $this->getSetMethodName($this->updatedMap['property']);
            foreach ($collection as $model) {
                // Always set the update timestamp
                $model->{$setMethod}(clone $now);
            }
        }
        return $collection;
    }


    /**
     * Reset the updated timestamp of `$object` in the database.
     *
     * Required as a workaround for manually set updated timestamp model values
     * being clobbered on save in `\Schoolbox\Mapper::populateSaveTimestamps`.
     *
     * @param object                   $object
     * @param Alaress_Date_Format|null $updatedAt
     */
    public function resetUpdatedTimestamp($object, ?Alaress_Date_Format $updatedAt): void
    {
        if ($this->updatedMap) {
            $this->updateAll(
                $object,
                $this->updatedMap['column'],
                ($updatedAt && !$updatedAt->isNull()) ? $updatedAt->getDatabase() : null
            );
        }
    }


    /**
     * Set model UUIDs (if this mapper has a UUID) prior to saving to DB
     *
     * @param Collection $collection
     * @return Collection
     */
    protected function populateUuids(Collection $collection): Collection
    {
        // First figure out which properties are actually UUIDs
        $uuidProperties = [];
        foreach ($this->map as $property => $mapping) {
            if (!empty($mapping['uuid'])) {
                $uuidProperties[] = $property;
            }
        }
        if (empty($uuidProperties)) {
            return $collection;
        }

        // Set each UUID property
        $query = $this->database->prepare('SELECT UUID()');
        foreach ($collection as $model) {
            foreach ($uuidProperties as $uuidProperty) {
                $existingUuid = $this->getModelProperty($model, $uuidProperty);
                if (empty($existingUuid)) {
                    $query->execute();
                    $this->setModelProperty($model, $uuidProperty, $query->fetchColumn());
                    $query->closeCursor();
                }
            }
        }
        return $collection;
    }


    /**
     * Asserts that the joined model that `$model` joins to is the same as what
     * is persisted.
     *
     * @param object      $model   The object to check
     * @param string      $join    The join to check
     * @param null|string $message If `$model`'s joined model has changed,
     *                             throw an exception with this message.
     * @throws Mapper\DefinitionException if join `$join` isn't on this mapper
     * @throws \UnexpectedValueException  if `$model`'s joined model has changed
     */
    protected function assertJoinedModelNotChanged($model, string $join, ?string $message = null): void
    {
        // Does this mapper have an identifier column?
        // If not I can't tell if this is changed or not.
        if (!$this->hasIdentifier()) {
            return;
        }

        // What's the model's identifier value?
        // If it is empty, then the model is not yet persisted, and can't be
        // considered to be changed.
        $id = $this->getModelProperty($model, $this->getIdentifierProperty());
        if (!$id) {
            return;
        }

        // Verify that this model's joined model hasn't changed
        $joinDefinition   = $this->getJoinDefinition($join);
        $foreignKey       = $joinDefinition['this_key'];
        $newFkValue       = $this->getModelProperty($model, $this->getPropertyFromColumn($foreignKey));
        $existingFkValues = $this->findScalarByPredicates(
            $foreignKey,
            $this->getPredicateBuilder()->equals($this->getIdentifierColumn(), $id)
        );
        if (!empty($existingFkValues)) {
            $existingFkValue = array_pop($existingFkValues);
            if ($newFkValue !== $existingFkValue) {
                throw new \UnexpectedValueException($message ?? 'Joined model has changed');
            }
        }
    }


    /**
     * @inheritDoc
     */
    public function getColumnScalarHydrator(string $column, int $depth = 0): IColumnType
    {
        // If the column is a foreign key to another entity, we need to fetch that other entity's column hydrator.
        // This ensures we always returning a hydrator whose hydrate() method generates a scalar data type.
        // We know the data types are the same as that's a pre-requisite for adding the foreign key.
        // It quite possible for a FK to point to a column that is itself a FK.
        // This therefore needs to be recursive to follow these relationships.

        // Base Case 1:
        //   Infinite recursion check. FK loops are technically possible at a schema level.
        //   But unlikely we have a valid chain this long.
        if ($depth > 1000) {
            // This will intentionally throw a TypeError
            throw new TypeError('Depth exceeded 1000: ' . $depth);
        }

        /** @var IColumnType $hydrator */
        $hydrator = $this->map->getOffsetByColumn($column)['hydrator'];

        // Base Case 2:
        //   Column is a scalar. Respect declared data type.
        if ($hydrator->isScalar()) {
            return $hydrator;
        }

        // Base Case 3:
        //   The hydrator is not scalar, but is also not for a Model
        if (!$hydrator instanceof ModelColumn) {
            // Don't hydrate at all. Raw data type (null|string) will be returned.
            return new Column\Base();
        }

        // Recursive Case:
        //   Hydrator is a Model Hydrator.
        $joinDef = $this->map->getOffsetByColumn($column)['join_def'];
        /** @var self $otherMapper */
        $otherMapper = $joinDef['mapper'];
        $otherMapper = $otherMapper instanceof IMapper ? $otherMapper : $otherMapper::getInstance();
        $otherColumn = $joinDef['other_key'];
        return $otherMapper->getColumnScalarHydrator($otherColumn, $depth + 1);
    }
}
