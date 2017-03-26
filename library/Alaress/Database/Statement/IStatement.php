<?php

/**
 * Interface for statement
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement;

use Alaress\Database\Iterator\IIterator;
use PDO;

interface IStatement
{
    /**
     * Get number of rows affected by last executed query
     *
     * @return int
     */
    function rowCount();

    /**
     * Get single record from last executed query
     *
     * @param int $fetchStyle PDO fetch style
     * @return array
     */
    function fetch($fetchStyle = PDO::FETCH_ASSOC);

    /**
     * Get all records from last executed query.
     *
     * Do not use this on threat of death. This thing is the definition of pure evil, and
     * you should be iterating instead.
     *
     * @param int $fetchStyle PDO fetch style
     *
     * @deprecated
     * @return mixed
     */
    function fetchAll($fetchStyle = PDO::FETCH_ASSOC);

    /**
     * Get single column denoted by its index for the next row in the query
     *
     * @param int $column
     * @return string
     */
    function fetchColumn($column = 0);

    /**
     * Get a single column from every row as an array
     *
     * @param int|string $column Column name/index to be used as array value
     * @param null|string $index Optional column name/index to be used as array index
     * @return array
     */
    function fetchColumnArray($column, $index = null);

    /**
     * Returns all query results in an array indexed by a single columns value.
     *
     * Example SQL: SELECT id, `firstname`, `lastname` FROM `user`
     * Example PHP: var_dump(fetchRowArray('id'));
     *
     * Psuedo-Execution Result
     *
     * [
     *   173 => [
     *      `id` => 173,
     *      'firstname' => 'Rachel',
     *      'lastname' => 'Rhodes'
     *   ],
     *   203 => [
     *      `id` => 203,
     *      'firstname' => 'David',
     *      'lastname' => 'Rhodes'
     *   ]
     * ]
     *
     * @param string $index Column name/index to be used as array index
     * @return array
     */
    function fetchRowArray($index);

    /**
     * Returns last error message this statement generated
     *
     * @return string
     */
    function errorInfo();

    /**
     * Returns last error message this statement generated
     *
     * @return string
     */
    function errorCode();

    /**
     * Moves to the next result set in a stored procedure that returns multiple sets of results
     *
     * @return bool
     */
    function nextRowset();

    /**
     * This function should manipulate/generate and return the SQL statement to be executed
     *
     * @return string
     */
    function getSql();

    /**
     * Execute query
     *
     * @param array|null $values
     * @return bool
     */
    function execute($values = null);

    /**
     * Execute stored procedure and return an iterator
     *
     * @param array|null $values
     * @return IIterator|bool
     */
    function executeIterator($values = null);

    /**
     * Returns the data type name as a string for debugging display purposes only, should be no
     * other reason to use this
     *
     * @param mixed $value Value to get data type name for
     * @param int $type Override data type
     * @return string
     */
    static function getDataTypeName($value, $type = null);
}
