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
     * Quotes column, table, etc. names so they may be safely used in queries.
     * For MySQL: ` in an identifier is replaced with ``, and the string is
     * wrapped with ` characters.
     *
     * @param  string $string
     * @return string
     */
    public function quoteIdentifier($string);

    /**
     * Wrapper for PDO::quote
     *
     * @param string $string
     * @param int    $parameter_type
     * @return string
     */
    public function quote($string, $parameter_type = PDO::PARAM_STR);

    /**
     * Escapes _ and % characters within $string, so they don't have unintended
     * effects in LIKE queries.
     *
     * This doesn't quote $string for safe use in string-interpolated queries,
     * it only escapes LIKE chars.
     *
     * @param  string $string
     * @return string
     */
    public function quoteForLike($string);

}
