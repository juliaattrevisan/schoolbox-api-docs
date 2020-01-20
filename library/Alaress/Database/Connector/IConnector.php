<?php

/**
 * Interface for connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Collector;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Predicate\IBuilder;
use Alaress\Database\Quoter\IQuoter;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;
use PDOException;

interface IConnector extends IQuoter
{
    /**
     * Connect to database
     *
     * @param $hostname
     * @param $database
     * @param $username
     * @param $password
     * @param array $options
     * @return bool
     */
    public function connect($hostname, $database, $username, $password, $options = []);

    /**
     * Reconnect to the database (optionally- with new options)
     *
     * @param array|null $options
     * @return bool
     */
    public function reconnect($options = null);

    /**
     * Enable the use of multiple active result sets (required if executing a query inside an iterator).
     * This may or may not be supported by all database connectors- where it is not it will just do nothing.
     *
     * @return $this
     */
    public function enableMultipleActiveResultSets();

    /**
     * Disable the use of multiple active result sets (better performance)
     *
     * @return $this
     */
    public function disableMultipleActiveResultSets();

    /**
     * Set the timezone on the connection (Note this is only appplicable on MySQL connections)
     *
     * @param string $timezone A valid timezone
     */
    public function setTimezone($timezone);

    // ----------------------------------------------------------------------------------------------------------------
    // Query preparation functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Executes a query and returns true/false to indicate success
     *
     * @param string $sql
     * @return bool
     * @throws \Alaress\Database\Exception
     */
    public function execute($sql);

    /**
     * Prepare database query and return a statement
     *
     * @param string $query
     * @param array $parameters
     * @return IQuery
     */
    public function prepareQuery($query, $parameters = []);

    /**
     * Convenience methods that prepares and executes a query with the supplied parameters
     *
     * @param string $sql
     * @param null|array $parameters
     * @return IQuery|\Alaress\Database\Statement\Query\Base
     * @throws \Alaress\Database\Exception
     */
    public function executeQuery($sql, $parameters = null);

    /**
     * Retrieve an iterator for a SQL query
     *
     * @param string $sql
     * @param null|array $parameters
     * @return IIterator
     */
    public function getQueryIterator($sql, $parameters = null);

    /**
     * Prepare database function call
     *
     * @param string $function Name of the function to execute
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IStoredProcedure
     */
    public function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Convenience method to prepare and execute a function call with the supplied parameters
     *
     * @param string $function
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IFunctionCall
     */
    public function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Prepare database stored procedure
     *
     * @param string $procedure Name of the stored procedure to execute
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IStoredProcedure
     */
    public function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Convenience method to prepare and execute a stored procedure with the supplied parameters
     *
     * @param string $procedure
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IStoredProcedure
     */
    public function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Retrieve an iterator for a stored procedure
     *
     * @param string $procedure
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IIterator
     */
    public function getStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Return last inserted record ID
     *
     * @param string|null $name
     * @return string
     */
    public function lastInsertId($name = null);

    // ----------------------------------------------------------------------------------------------------------------
    // Database transaction functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Wraps `$callback` in a transaction.
     *
     * @param callable $callback
     * @return IConnector
     * @throws \Throwable
     */
    public function transact(callable $callback);

    /**
     * If not in a transaction, call `$callback` immediately.
     *
     * Otherwise, queue `$callback` to be called only if the outermost nesting
     * level is committed.
     *
     * @param callable $callback
     * @return IConnector
     */
    public function onPersist(callable $callback);

    /**
     * Initiates a transaction
     *
     * @return bool Returns TRUE on success or FALSE on failure
     * @throws PDOException if there is already a transaction started or the driver does not support transactions
     */
    public function beginTransaction();

    /**
     * Check if we are in a database transaction
     *
     * @return bool Returns TRUE if a transaction is currently active, and FALSE if not
     */
    public function inTransaction();

    /**
     * Commit current transaction to database
     *
     * @return bool Returns TRUE on success or FALSE on failure
     * @throws PDOException If there is no active transaction.
     *                      Note: An exception is raised even when the PDO::ATTR_ERRMODE attribute is not PDO::ERRMODE_EXCEPTION.
     */
    public function commit();

    /**
     * Rollback current transaction
     *
     * @return bool Returns TRUE on success or FALSE on failure
     * @throws PDOException if there is no active transaction
     */
    public function rollBack();

    // ----------------------------------------------------------------------------------------------------------------
    // Getters for immutable properties
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Returns last error message from database connection
     *
     * @return string
     */
    public function errorInfo();

    /**
     * Returns last error code from database connection
     *
     * @return string
     */
    public function errorCode();

    /**
     * Return hostname used for connection
     *
     * @return string
     */
    public function getHostname();

    /**
     * Return port used for connection
     *
     * @return string
     */
    public function getPort();

    /**
     * Return username used for connection
     *
     * @return string
     */
    public function getUsername();

    /**
     * Return password used for connection
     *
     * @return string
     */
    public function getPassword();

    /**
     * Return database name used for connection
     *
     * @return string
     */
    public function getDatabase();

    /**
     * Return database collector for debugging
     *
     * @return Collector
     */
    public function getCollector();

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

    // ----------------------------------------------------------------------------------------------------------------
    // Lazy temporary clutch to build IN portion of queries, should integrate this things into the
    // predicate builder
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Create an array with SQL and matching bindings for an IN query
     * TODO: Investigate that predicate builder thing
     *
     * @param $externalIds
     * @param string $prefix
     * @return mixed
     */
    public function prepareInBindings($externalIds, $prefix = 'param');

    /**
     * Returns a predicate builder for this connector's database.
     *
     * @param string $table The table to build the predicate builder for
     * @return IBuilder
     */
    public function buildPredicateBuilder($table);
}
