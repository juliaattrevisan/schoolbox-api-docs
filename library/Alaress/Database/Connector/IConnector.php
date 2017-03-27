<?php

/**
 * Interface for connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Collector;
use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Statement\FunctionCall\IFunctionCall;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;

interface IConnector
{
    /**
     * Reconnect to the database (optionally- with new options)
     *
     * @param array|null $options
     * @return bool
     */
    function reconnect($options = null);

    // ----------------------------------------------------------------------------------------------------------------
    // Query preparation functions
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Executes a query and returns true/false to indicate success
     *
     * @param string $sql
     * @return bool
     */
    function execute($sql);

    /**
     * Prepare database query and return a statement
     *
     * @param string $query
     * @param array $parameters
     * @return IQuery
     */
    function prepareQuery($query, $parameters = []);

    /**
     * Convenience methods that prepares and executes a query with the supplied parameters
     *
     * @param string $sql
     * @param null|array $parameters
     * @return IQuery
     */
    function executeQuery($sql, $parameters = null);

    /**
     * Convenience method to prepare and execute a query with the supplied parameters and return a
     * ready to use iterator
     *
     * @param string $sql
     * @param null|array $parameters
     * @return IIterator
     */
    function executeQueryIterator($sql, $parameters = null);

    /**
     * Prepare database function call
     *
     * @param string $function Name of the function to execute
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IStoredProcedure
     */
    function prepareFunctionCall($function, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Convenience method to prepare and execute a function call with the supplied parameters
     *
     * @param string $function
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IFunctionCall
     */
    function executeFunctionCall($function, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Prepare database stored procedure
     *
     * @param string $procedure Name of the stored procedure to execute
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IStoredProcedure
     */
    function prepareStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Convenience method to prepare and execute a stored procedure with the supplied parameters
     *
     * @param string $procedure
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IStoredProcedure
     */
    function executeStoredProcedure($procedure, $inputs = null, &$outputs = null, &$return = null);

    /**
     * Convenience method to prepare and execute a stored procedure with the supplied parameters and
     * return a ready to use iterator
     *
     * @param string $procedure
     * @param null|array &$inputs
     * @param null|array &$outputs
     * @param null|array &$return
     * @return IIterator
     */
    function executeStoredProcedureIterator($procedure, $inputs = null, &$outputs = null, &$return = null);

    // ----------------------------------------------------------------------------------------------------------------
    // Getters for immutable properties
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Returns last error message from database connection
     *
     * @return string
     */
    function errorInfo();

    /**
     * Returns last error code from database connection
     *
     * @return string
     */
    function errorCode();

    /**
     * Return hostname used for connection
     *
     * @return string
     */
    function getHostname();

    /**
     * Return port used for connection
     *
     * @return string
     */
    function getPort();

    /**
     * Return username used for connection
     *
     * @return string
     */
    function getUsername();

    /**
     * Return password used for connection
     *
     * @return string
     */
    function getPassword();

    /**
     * Return database name used for connection
     *
     * @return string
     */
    function getDatabase();

    /**
     * Return database collector for debugging
     *
     * @return Collector
     */
    function getCollector();

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
    function prepareInBindings($externalIds, $prefix = 'param');
}