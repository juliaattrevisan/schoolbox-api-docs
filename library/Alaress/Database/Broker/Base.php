<?php

/**
 * This abstract defines functions and properties common to connectors for all databases. It omits
 * any function that requires a database type specific object.
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Broker;

use Alaress\Database\Collector;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Statement\Query\Base as Query;
use Alaress\DebugBar\Instance as DebugBar;
use DebugBar\DataCollector\DataCollectorInterface;
use PDO;
use PDOException;

abstract class Base implements IBroker
{
    /**
     * Login timeout (seconds)
     */
    public const LOGIN_TIMEOUT = 2;

    /**
     * Default query timeout (seconds)
     */
    public const QUERY_TIMEOUT = 30;

    /**
     * Number of bind parameter for one query.
     * This is used for TASS and CISPL custom query
     */
    public const MAX_BIND_NUMBER = 1000;

    /** @var array $options Connection options */
    protected $options = [];

    /** @var IConnector $connector Link back to the connector object that invoked the broker */
    protected $connector;

    /** @var PDO $pdo The PDO object that does all the real work */
    protected $pdo;

    /** @var Collector The debugging collector */
    protected $collector = null;

    /**
     * Optional PDO connection options, set when constructing the PDO object.
     *
     * @var array
     */
    protected $connectionOptions = [];

    /**
     * Optional PDO database attributes, set after constructing the PDO object.
     *
     * @var array
     */
    protected $databaseAttributes = [];

    /** @var array $postConnectQueries Optional SQL queries to be run after connection */
    protected $postConnectQueries = [];

    /** @var callable[] $postProcessors This contains an array of callbacks that need to be run on all database queries */
    protected $postProcessors = [];

    /**
     * @inheritdoc
     */
    public function setConnector($connector)
    {
        $this->connector = $connector;
    }

    /**
     * @inheritdoc
     */
    public function disconnect()
    {
        // This personally irks me quite badly as there is no disconnect function in PDO- they just say we need to unset or NULL out the
        // variable and let PHP garbage collection get around to it. How about you trust us to close the damn socket!

        unset($this->pdo);

        // Force GC because im cynical and don't trust the magical fairies to clean up after me

        gc_collect_cycles();
    }

    /**
     * @inheritdoc
     */
    public function connect($options = [])
    {
        $this->options = $options;

        // Close any existing connection (this seems a bit flaky- why is there no close function?)

        unset($this->pdo);

        // Set any database connection options that we want to allow

        $connectOptions = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];

        $connectOptions += $this->connectionOptions;
        // Due to ambiguity in PDO specification, the PDO_SQLSRV driver doesn't respect the PDO::ATTR_TIMEOUT variable as indicated
        // in the Microsoft blog post (https://blogs.msdn.microsoft.com/brian_swan/2010/04/27/design-decisions-in-the-pdo-driver-for-sql-server/)
        //
        // Instead they use a custom attribute for login timeout. Unfortunately the operating system gets in the way here as well, and we need to
        // set the socket timeout to the same value as the login timeout for this login timeout attribute to have the desired effect. So as not to
        // blow things up prematurely later in the system, we revert the timeout as soon as we have created the PDO connection.

        $defaultSocketTimeout = ini_get('default_socket_timeout');

        ini_set('default_socket_timeout', self::LOGIN_TIMEOUT);

        try {
            // Create a new PDO instance using the DSN string and supplied username/password parameters

            $this->pdo = $this->buildPdoConnection($connectOptions);
        } catch (PDOException $e) {
            // Need to restore timeout both here and in normal control flow

            ini_set('default_socket_timeout', $defaultSocketTimeout);

            // Something went wrong during connection

            throw new DatabaseException("Error connecting to database.\nDSN String: {$this->getDsn()}\nPDO Error: {$e->getMessage()}");
        }

        // Restore default socket timeout

        ini_set('default_socket_timeout', $defaultSocketTimeout);

        // These will only get called if the child class overrides the values- don't *think*
        // we need these to be parameters as they're really just there to get the database in a
        // state where our functions operate as expected

        foreach ($this->databaseAttributes as $attribute => $value) {
            $this->pdo->setAttribute($attribute, $value);
        }

        foreach ($this->postConnectQueries as $sql) {
            $this->pdo->exec($sql);
        }

        // Don't do this until after connecting
        // (probably on whatever the first query is?)
        if (DebugBar::isEnabled()) {
            $this->installCollector();

            /** @var DataCollectorInterface $collector */
            $collector = $this->getCollector();

            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function enableMultipleActiveResultSets()
    {
        // This feature is not supported by all databases, hence the base method does nothing
    }

    /**
     * @inheritdoc
     */
    public function disableMultipleActiveResultSets()
    {
        // This feature is not supported by all databases, hence the base method does nothing
    }

    // ----------------------------------------------------------------------------------------------------------------
    // This performs the low level PDO statement preparation
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function prepare($query, $options = [])
    {
        return $this->pdo->prepare($query, $options);
    }

    /**
     * @inheritdoc
     */
    public function prepareQuery($query)
    {
        return new Query($this, $query);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Debugging collector
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function getCollector()
    {
        return $this->collector;
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

    /**
     * @inheritdoc
     */
    public function getHostname()
    {
        return $this->connector->getHostname();
    }

    /**
     * @inheritdoc
     */
    public function getDatabase()
    {
        return $this->connector->getDatabase();
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Helper method for building PDO connections.
     *
     * Some implementations need to override this method, as they may need to
     * escape the username or password differently.
     *
     * @param array $connectOptions
     * @return PDO
     */
    protected function buildPdoConnection(array $connectOptions): PDO
    {
        return new PDO(
            $this->getDsn(),
            $this->connector->getUsername(),
            $this->connector->getPassword(),
            $connectOptions,
        );
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
