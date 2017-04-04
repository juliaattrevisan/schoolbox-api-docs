<?php

/**
 * Interface for database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Broker;

use Alaress\Database\Collector;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use DebugBar\DataCollector\PDO as DebugPDO;
use PDOStatement;

interface IBroker
{
    /**
     * Sets the connector that the broker will use
     *
     * @param IConnector $connector
     */
    public function setConnector($connector);

    /**
     * Create DSN string from connector properties
     *
     * @return string
     */
    public function getDsn();

    /**
     * Retrieve the hostname from connector
     *
     * @return string
     */
    public function getHostname();

    /**
     * Retrieve the database name from the connector
     *
     * @return string
     */
    public function getDatabase();

    /**
     * Connects to the actual database
     *
     * @param array $options Optional array of connection parameters (currently only 'debug')
     * @return bool
     * @throws DatabaseException
     */
    public function connect($options = []);

    // ----------------------------------------------------------------------------------------------------------------
    // Database query execution
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Prepare a cached PDO statement
     *
     * @param string $query
     * @param array $options
     * @return PDOStatement
     */
    public function prepare($query, $options = []);

    // ----------------------------------------------------------------------------------------------------------------
    // Database statement generators
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Returns a type specific iterator
     *
     * @param IStatement $statement
     * @param PDOStatement $pdoStatement
     * @return IIterator
     */
    public function getIterator($statement, $pdoStatement);

    /**
     * Prepare database query
     *
     * @param string $query
     * @return IQuery
     */
    public function prepareQuery($query);

    /**
     * Prepare database function call
     *
     * @param string $function Name of the function to execute
     * @return IFunctionCall
     */
    public function prepareFunctionCall($function);

    /**
     * Prepare database stored procedure
     *
     * @param string $procedure Name of the stored procedure to execute
     * @return IStoredProcedure
     */
    public function prepareStoredProcedure($procedure);

    // ----------------------------------------------------------------------------------------------------------------
    // Database transaction functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Begin database transaction
     * @return bool
     */
    public function beginTransaction();

    /**
     * Return true/false if in database transaction
     *
     * @return bool
     */
    public function inTransaction();

    /**
     * Commit database transaction
     *
     * @return bool
     */
    public function commit();

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollBack();

    /**
     * Return last inserted record ID
     *
     * @param string|null $name
     * @return string
     */
    public function lastInsertId($name = null);

    // ----------------------------------------------------------------------------------------------------------------
    // Debugging collector functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Retrieves the query collector from this connection. Will fail if debugging is disabled.
     *
     * @return Collector
     */
    public function getCollector();

    /**
     * Installs the PHP debug bar collector into this object.
     */
    public function installCollector();

    // ----------------------------------------------------------------------------------------------------------------
    // Post processor stuff
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Add a post processor to this connection, callable must accept either a string/array and return the same type
     * after whatever processing is required.
     *
     * @param callable $callable
     */
    public function addPostProcessor($callable);

    /**
     * Return array of post processors
     *
     * @return callable[]
     */
    public function getPostProcessors();
}