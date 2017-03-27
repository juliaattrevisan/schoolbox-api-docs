<?php

/**
 * This abstract defines functions and properties common to connectors for all databases. It omits
 * any function that requires a database type specific object.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Broker;

use Alaress\Cache;
use Alaress\Database\Collector;
use Alaress\Database\Connector\IConnector;
use DebugBar\DataCollector\DataCollectorInterface;
use PDO;
use PDOException;
use PDOStatement;
use DebugBar\DataCollector\PDO as DebugPDO;
use Alaress\Database\Exception as DatabaseException;
use Alaress\DebugBar\Instance as DebugBar;

abstract class Base implements IBroker
{
    /** @var IConnector $connector Link back to the connector object that invoked the broker */
    protected $connector;

    /** @var PDO $pdo The PDO object that does all the real work */
    protected $pdo;

    /** @var Cache|PDOStatement[] $statementCache PDO Prepared statement cache */
    protected $statementCache = [];

    /** @var Collector The debugging collector */
    protected $collector = null;

    /** @var array $databaseAttributes Optional PDO database attributes that can be set after connection */
    protected $databaseAttributes = [];

    /** @var array $postConnectQueries Optional SQL queries to be run after connection */
    protected $postConnectQueries = [];

    /** @var callable[] $postProcessors This contains an array of callbacks that need to be run on all database queries */
    protected $postProcessors = [];

    /**
     * Constructor. Sets up the statement cache, and grabs a handle to the connector that holds all
     * of the relevant information we require to connect
     *
     * @param IConnector $connector The connector that this broker belongs to
     */
    public function __construct($connector)
    {
        // Keep a handle on the connector that this broker belongs to as this is where all of the
        // connection related settings (hostname, username, password, etc) are stored

        $this->connector = $connector;

        // Setup PDO statement cache

        $this->statementCache = new Cache(function ($statement, $options) {
            return $this->pdo->prepare($statement, $options);
        });
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        // Close any existing connection (this seems a bit flaky- why is there no close function?)

        unset($this->pdo);

        // Set any database connection options that we want to allow

        $options = [
            PDO::ATTR_PERSISTENT => isset($options['debug']) ? $options['debug'] : false,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        try {

            // Create a new PDO instance using the DSN string and supplied username/password parameters

            $this->pdo = new PDO(
                $this->getDsn(),
                $this->connector->getUsername(),
                $this->connector->getPassword(),
                $options
            );

            // These will only get called if the child class overrides the values- don't *think*
            // we need these to be parameters as they're really just there to get the database in a
            // state where our functions operate as expected

            foreach ($this->databaseAttributes as $attribute => $value) {
                $this->pdo->setAttribute($attribute, $value);
            }

            foreach ($this->postConnectQueries as $sql) {
                $this->pdo->exec($sql);
            }

            if (DebugBar::isEnabled()) {
                $this->installCollector();

                /** @var DataCollectorInterface $collector */
                $collector = $this->getCollector();

                if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                    DebugBar::getInstance()->addCollector($collector);
                }
            }

        } catch (PDOException $e) {

            // Something went wrong during connection

            throw new DatabaseException("Error connecting to database.\nDSN String: {$this->getDsn()}\nPDO Error: {$e->getMessage()}");
        }

        return true;
    }

    // ----------------------------------------------------------------------------------------------------------------
    // This performs the low level PDO statement preparation via the caching mechanism
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function prepare($query, $options = [])
    {
        return $this->statementCache->get(sha1($query), [$query, $options]);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // PDO pass-through functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function lastInsertId($name = null)
    {
        return $this->pdo->lastInsertId($name);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Database transaction stuff
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * @inheritdoc
     */
    public function inTransaction()
    {
        return $this->pdo->inTransaction();
    }

    /**
     * @inheritdoc
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * @inheritdoc
     */
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Debugging collector
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function getCollector()
    {
        $this->pdo = new DebugPDO\TraceablePDO($this->pdo);
        return new DebugPDO\PDOCollector($this->pdo);
    }

    /**
     * @inheritdoc
     */
    public function installCollector()
    {
        if (!($this->collector instanceof Collector)) {
            $this->collector = new Collector($this);
        }
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Post processor callback stuff
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function addPostProcessor($callable)
    {
        $this->postProcessors[] = $callable;
    }

    /**
     * @inheritdoc
     */
    public function getPostProcessors()
    {
        return $this->postProcessors;
    }
}