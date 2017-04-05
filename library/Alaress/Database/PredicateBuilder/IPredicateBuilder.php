<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database\PredicateBuilder;

/**
 * Interface for predicate builder implementations.
 */
interface IPredicateBuilder
{
    /**
     * Query the intersection (AND) of the conditions in the given predicate builder
     *
     * @param IPredicateBuilder $predicate
     * @return IPredicateBuilder
     */
    public function all(IPredicateBuilder $predicate);

    /**
     * Query the union (OR) of the conditions in the given predicate builder
     *
     * @param IPredicateBuilder $predicate
     * @return IPredicateBuilder
     */
    public function any(IPredicateBuilder $predicate);

    /**
     * @param string $columnName
     * @return IPredicateBuilder
     */
    public function isNull($columnName);

    /**
     * @param string $columnName
     * @return IPredicateBuilder
     */
    public function notNull($columnName);

    /**
     * @param string $columnName
     * @return IPredicateBuilder
     */
    public function isTrue($columnName);

    /**
     * @param string $columnName
     * @return IPredicateBuilder
     */
    public function isFalse($columnName);

    /**
     * @param string     $columnName
     * @param string|int $columnValue
     * @return IPredicateBuilder
     */
    public function equals($columnName, $columnValue);

    /**
     * @param string     $columnName
     * @param string|int $columnValue
     * @return IPredicateBuilder
     */
    public function notEquals($columnName, $columnValue);

    /**
     * If $columnValue is an array, then the values are combined using 'OR'
     * eg, get student names where 'name' LIKE ['A%', 'B%'] would match both 'Andy' and 'Bob'.
     *
     * To get student names that match ('A%' AND '%Y') you need to chain two calls to like()
     *
     * @param string $columnName
     * @param string $columnValue Used directly in LIKE statement.
     *                            @see \Database::escapeForLike for escaping user input.
     * @return IPredicateBuilder
     */
    public function like($columnName, $columnValue);

    /**
     * If $columnValue is an array, then the values are combined using 'AND'
     *
     * This is because in boolean logic !(T || T) === (F && F)
     *
     * eg, get student names NOT LIKE ['A%', '%c'] would match 'Bob' but not match 'Andy'
     *
     * Semantically, if like() returns X items from N, then notLike() should return (N - X) items
     *
     * @param string $columnName
     * @param string $columnValue Used directly in NOT LIKE statement.
     *                            @see \Database::escapeForLike for escaping user input.
     * @return IPredicateBuilder
     */
    public function notLike($columnName, $columnValue);

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return IPredicateBuilder
     */
    public function lessThan($columnName, $columnValue);


    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return IPredicateBuilder
     */
    public function greaterThan($columnName, $columnValue);

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return IPredicateBuilder
     */
    public function notLessThan($columnName, $columnValue);

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return IPredicateBuilder
     */
    public function notGreaterThan($columnName, $columnValue);

    /**
     * Adds predicates for checking if this value is between two values.
     *
     * @param string $columnName
     * @param mixed  $from If null, this value is excluded.
     * @param mixed  $to   If null, this value is excluded.
     * @return IPredicateBuilder
     */
    public function between($columnName, $from, $to);

    /**
     * Adds predicates for checking if this value is between two dates.
     *
     * @param string               $columnName
     * @param \Alaress_Date_Format $from If null, this value is excluded.
     * @param \Alaress_Date_Format $to   If null, this value is excluded.
     * @param string               $compareFormat
     * @return IPredicateBuilder
     */
    public function betweenDates(
        $columnName,
        \Alaress_Date_Format $from = null,
        \Alaress_Date_Format $to = null,
        $compareFormat = \Alaress_Date_Format::DATABASE_FORMAT
    );

    /**
     * Retrieves all predicates that have been added to this builder.
     *
     * The result of this method may be concatenated into a single string
     * (joined with AND or OR), and injected into a WHERE part of a SQL query.
     * There may be query parameters - use `getParams()` to get this list.
     *
     * @return string[]
     */
    public function getWhere();

    /**
     * Retrieves all parameters that have been added to this builder.
     *
     * The result of this method may be used as the $params parameter of
     * `IConnector::executeQuery` and its related methods.
     *
     * @return mixed[]
     */
    public function getParams();
}
