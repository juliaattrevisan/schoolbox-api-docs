<?php

/**
 * Interface for connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Connector;

use Alaress\Database\Iterator\IIterator;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\StoredProcedure\IStoredProcedure;

interface IConnector {

    // ----------------------------------------------------------------------------------------------------------------
    // The function that actually starts the ball rolling
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * Connect to the database
     *
     * @param string $hostname
     * @param string $database
     * @param string $username
     * @param string $password
     * @param array $options Database connection options
     * @return bool
     */
    function connect($hostname, $database, $username, $password, $options = []);

    // ----------------------------------------------------------------------------------------------------------------
    // Query preparation functions
    // ----------------------------------------------------------------------------------------------------------------

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
}