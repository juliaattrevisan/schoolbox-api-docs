<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress;

use PDO;
use PDOStatement;

interface IDatabase
{

    /**
     * Wrapper for PDO::prepare
     * PDO::prepare prepares a statement for execution and returns a PDOStatement object.
     *
     * @param string $statement
     * @param array  $driverOptions
     * @return PDOStatement
     */
    public function prepare($statement, array $driverOptions = []);


    /**
     * Wrapper for PDO::query
     * PDO::query() executes an SQL statement in a single function call, returning the result set (if any)
     * returned by the statement as a PDOStatement object.

     * @param string $statement
     * @param array  $args
     * @return PDOStatement
     */
    public function query($statement, array $args = null);

    /**
     * Wrapper for PDO::quote
     *
     * @param string $string
     * @param int    $parameter_type
     * @return string
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR);

    /**
     * Begin transaction
     *
     * @return bool
     */
    public function beginTransaction();

    /**
     * Roll back current transaction
     *
     * @return bool
     */
    public function rollback();

    /**
     * Commit current transaction
     *
     * @return bool
     */
    public function commit();

    /**
     * Retrieves last insert ID
     *
     * @param  string $name Some databases accept a table name to fetch last ID from
     * @return string
     */
    public function lastInsertId($name = null);
}
