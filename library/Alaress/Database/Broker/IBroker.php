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
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\IStatement;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use PDO;
use PDOStatement;
use Alaress\Database\Exception as DatabaseException;
use DebugBar\DataCollector\PDO as DebugPDO;

interface IBroker
{
    /**
     * Create DSN string from connector properties
     *
     * @return string
     */
    function getDsn();

    /**
     * Connects to the actual database
     *
     * @param array $options Optional array of connection parameters (currently only 'debug')
     * @return bool
     * @throws DatabaseException
     */
    function connect($options = []);

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
    function prepare($query, $options = []);

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
    function getIterator($statement, $pdoStatement);

    /**
     * Prepare database query
     *
     * @param string $query
     * @return IQuery
     */
    function prepareQuery($query);

    /**
     * Prepare database function call
     *
     * @param string $function Name of the function to execute
     * @return IFunctionCall
     */
    function prepareFunctionCall($function);

    /**
     * Prepare database stored procedure
     *
     * @param string $procedure Name of the stored procedure to execute
     * @return IStoredProcedure
     */
    function prepareStoredProcedure($procedure);

    // ----------------------------------------------------------------------------------------------------------------
    // Database transaction functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Begin database transaction
     * @return bool
     */
    function beginTransaction();

    /**
     * Return true/false if in database transaction
     *
     * @return bool
     */
    function inTransaction();

    /**
     * Commit database transaction
     *
     * @return bool
     */
    function commit();

    /**
     * Rollback transaction
     *
     * @return bool
     */
    function rollBack();

    /**
     * Return last inserted record ID
     *
     * @param string|null $name
     * @return string
     */
    function lastInsertId($name = null);

    // ----------------------------------------------------------------------------------------------------------------
    // Debugging collector functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Retrieves the query collector from this connection. Will fail if debugging is disabled.
     *
     * @return Collector
     */
    function getCollector();

    /**
     * Installs the PHP debug bar collector into this object.
     */
    function installCollector();

    // ----------------------------------------------------------------------------------------------------------------
    // Post processor stuff
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Add a post processor to this connection, callable must accept either a string/array and return the same type
     * after whatever processing is required.
     *
     * @param callable $callable
     */
    function addPostProcessor($callable);

    /**
     * Return array of post processors
     *
     * @return callable[]
     */
    function getPostProcessors();
}