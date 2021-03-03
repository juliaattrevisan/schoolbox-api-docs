<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress;

use Alaress\Database\Quoter\IQuoter;
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
     * Returns the quoter that has been set up for this database connection.
     *
     * @return IQuoter
     */
    public function getQuoter(): IQuoter;

    /**
     * Wraps `$callback` in a transaction.
     *
     * If `$callback` throws an exception, rollback and rethrow exception
     * If `$callback()` === false, rollback
     * Otherwise, commit
     *
     * @param callable $callback
     * @return bool
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
     * @return $this
     */
    public function onPersist(callable $callback);

    /**
     * Begin transaction
     *
     * Usage example:
     * <code>
     * $db->beginTransaction();
     * try {
     *     // a set of updates which must be atomic
     * } catch (\Throwable $e) {
     *     // Rollback, and then rethrow to ensure that commit() is not called
     *     $db->rollback();
     *     throw $e;
     * }
     * // Commit only if the try/catch block has completed without error
     * $db->commit();
     * </code>
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
