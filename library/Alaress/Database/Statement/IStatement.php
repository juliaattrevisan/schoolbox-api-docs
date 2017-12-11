<?php

/**
 * Interface for statement
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Statement;

use Alaress\Database\Exception;
use Alaress\Database\Iterator\IIterator;
use PDO;
use PDOStatement;

interface IStatement
{
    /**
     * Get number of rows affected by last executed query
     *
     * @return int
     */
    public function rowCount();

    /**
     * Close statement (without MARS enabled you can only have one statement active at a time, this prevents
     * the need to iterate the results fully before moving on to another query)
     *
     * @return bool
     */
    public function close();

    /**
     * Get single record from last executed query
     *
     * @param int $fetchStyle PDO fetch style
     * @return array
     */
    public function fetch($fetchStyle = PDO::FETCH_ASSOC);

    /**
     * Get all records from last executed query.
     *
     * Make sure you really want to use this- it has the potential to be a memory hog. If you're iterating results, use an iterator!
     *
     * @param int $fetchStyle PDO fetch style
     * @return mixed
     */
    public function fetchAll($fetchStyle = PDO::FETCH_ASSOC);

    /**
     * Get single column denoted by its index for the next row in the query
     *
     * @param int $column
     * @return string
     */
    public function fetchColumn($column = 0);

    /**
     * Get a single column from every row as an array
     *
     * @param int|string $column Column name/index to be used as array value
     * @param null|string $index Optional column name/index to be used as array index
     * @return array
     */
    public function fetchColumnArray($column, $index = null);

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
    public function fetchRowArray($index);

    /**
     * Moves to the next result set in a stored procedure that returns multiple sets of results
     *
     * @return bool
     * @throws Exception if moving to next result set failed for some reason
     */
    public function nextRowset();

    /**
     * This function should manipulate/generate and return the SQL statement to be executed
     *
     * @return string
     */
    public function getSql();

    /**
     * This returns the underlying PDO statement. This is used by the debugging collector.
     *
     * @return PDOStatement
     */
    public function getPDOStatement();

    /**
     * Execute query
     *
     * @param array|null $values
     * @return bool
     * @throws Exception if execution failed for some reason
     */
    public function execute($values = null);

    /**
     * Execute stored procedure and return an iterator
     *
     * @param array|null $values
     * @return IIterator
     * @throws Exception
     */
    public function getIterator($values = null);

    /**
     * Returns the data type name as a string for debugging display purposes only, should be no
     * other reason to use this
     *
     * @param mixed $value Value to get data type name for
     * @param int $type Override data type
     * @return string
     */
    public static function getDataTypeName($value, $type = null);
}
