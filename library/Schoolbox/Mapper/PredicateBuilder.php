<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper;

use Alaress\IDatabase;
use Countable;
use InvalidArgumentException;
use Traversable;
use UnderflowException;

class PredicateBuilder
{
    const NOT  = '!';
    const LIKE = '~';

    /** @var IDatabase */
    protected $database;

    /** @var string */
    protected $table;

    /** @var array */
    protected $where = [];

    /** @var array */
    protected $params = [];


    /**
     * PredicateBuilder constructor.
     *
     * @param IDatabase $db
     * @param string    $table
     */
    public function __construct(IDatabase $db, $table)
    {
        $this->setTable($table);
        $this->database = $db;
    }


    /**
     * Convenience method for adding directly from a Mapper column list
     *
     * @param array $columnList
     * @return self
     */
    public function addColumnList(array $columnList)
    {
        foreach ($columnList as $columnName => $columnValue) {
            $split = preg_split('/\\s+/', $columnName, 2);
            if (count($split) > 1) {
                $this->addPredicate($split[0], $columnValue, $split[1]);
            } else {
                $this->addPredicate($columnName, $columnValue);
            }
        }

        return $this;
    }


    /**
     * @param string $columnName
     * @return PredicateBuilder
     */
    public function isNull($columnName)
    {
        return $this->addPredicate($columnName, null);
    }

    /**
     * @param string $columnName
     * @return PredicateBuilder
     */
    public function isNotNull($columnName)
    {
        return $this->addPredicate(self::NOT . $columnName, null);
    }

    /**
     * @param string $columnName
     * @return PredicateBuilder
     */
    public function isTrue($columnName)
    {
        return $this->addPredicate($columnName, true);
    }

    /**
     * @param string $columnName
     * @return PredicateBuilder
     */
    public function isFalse($columnName)
    {
        return $this->addPredicate($columnName, false);
    }

    /**
     * @param string $columnName
     * @param array  $columnValues
     * @return PredicateBuilder
     */
    public function anyOf($columnName, array $columnValues)
    {
        return $this->addPredicate($columnName, $columnValues);
    }

    /**
     * @param string     $columnName
     * @param string|int $columnValue
     * @return PredicateBuilder
     */
    public function equals($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue);
    }

    /**
     * @param string     $columnName
     * @param string|int $columnValue
     * @return PredicateBuilder
     */
    public function notEquals($columnName, $columnValue)
    {
        return $this->addPredicate(self::NOT.$columnName, $columnValue);
    }

    /**
     * @param string $columnName
     * @param string $columnValue Used directly in LIKE statement.
     *                            @see \Database::quoteForLike for escaping user input.
     * @return PredicateBuilder
     */
    public function like($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue, 'LIKE');
    }

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return PredicateBuilder
     */
    public function lessThan($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue, '<');
    }


    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return PredicateBuilder
     */
    public function greaterThan($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue, '>');
    }


    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return PredicateBuilder
     */
    public function notLessThan($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue, '>=');
    }


    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return PredicateBuilder
     */
    public function notGreaterThan($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue, '<=');
    }


    /**
     * @param string $columnName
     * @param mixed  $columnValue
     * @param string $operand
     * @throws UnderflowException if an empty IN is attempted
     * @return self
     */
    protected function addPredicate($columnName, $columnValue, $operand = '=')
    {
        // check for valid column definition
        if (!preg_match('`^([^a-z0-9_\.]*)([a-z0-9_\.]+)$`i', $columnName, $matches)) {
            throw new InvalidArgumentException("Invalid column name '$columnName' given.");
        }

        // strip off any modifiers
        list (, $modifiers, $columnName) = $matches;

        // prepare known modifiers
        $negate = strpos($modifiers, self::NOT)  !== false;
        $fuzzy  = strpos($modifiers, self::LIKE) !== false;

        // accommodate joins
        $xpl = explode('.', $columnName) ?: [];

        if (count($xpl) == 1) {
            $table = $this->getTable();
            $field = $xpl[0];
        } elseif (count($xpl) == 2) {
            $table = $xpl[0];
            $field = $xpl[1];

            // remove the period so SQL doesn't freak out
            $columnName = str_replace('.', '_', $columnName);

        } else {
            // bad, but ignorable
            return $this;
        }

        if ($negate) {
            $whereNot = "NOT ";
            $operand  = "<>";
        } else {
            $whereNot = "";
        }

        $escaped_name = $this->database->quoteIdentifier($table) . '.' . $this->database->quoteIdentifier($field);

        if (!isset($columnValue)) {
            $this->where[] = "$escaped_name IS {$whereNot}NULL";

        } elseif ($columnValue === true) {
            $this->where[] = "$escaped_name IS {$whereNot}TRUE";

        } elseif ($columnValue === false) {
            $this->where[] = "$escaped_name IS {$whereNot}FALSE";

        } else {
            // This method is smart enough to work with arrays and IN statements
            // If the list of values is empty, though: there's no way
            // that the query could ever return anything, so just return
            // an empty collection.

            if ($this->canTraverse($columnValue)) {

                if (count($columnValue) === 0) {
                    // bad, and not ignorable
                    throw new UnderflowException('List of column values is empty');
                }

                if ($fuzzy) {
                    // fuzzy match: use (LIKE :x OR LIKE :y)
                    $likeParts = [];

                    foreach ($this->getPlaceholders($columnValue, $columnName) as $option) {
                        $likeParts[] = "$escaped_name {$whereNot}$option";
                    }

                    $this->where[] = '(' . implode(' OR ', $likeParts) . ')';

                } else {
                    // exact match: use IN
                    $this->where[] = "$escaped_name {$whereNot}IN {$this->getArrayPlaceholders($columnValue, $columnName)}";
                }

                $this->params  = array_merge($this->params, $this->getArrayParameters($columnValue, $columnName));

            } else {
                // alias the param
                $a = $this->getNonCollidingParamAlias($columnName);

                if ($fuzzy || ($operand == '=' && strpos($columnValue, '%') !== false)) {
                    // if wildcards are in the value, do a LIKE search rather than equals
                    $operand = "{$whereNot}LIKE";
                }

                $this->where[] = "$escaped_name $operand :$a";
                $this->putParam($a, $columnValue);
            }
        }

        return $this;
    }


    /**
     * Gets a string of placeholders for an array - usually used for creating a
     * list of placeholders for an IN condition.
     *
     * @param array  $input
     * @param string $prefix If not supplied, ? will be used as the placeholder
     *                       for all values.
     *
     * @return string Something like "(:id_0, :id_1, :id_2)".
     */
    public static function getArrayPlaceholders($input, $prefix = null)
    {
        return '(' . implode(',', self::getPlaceholders($input, $prefix)) . ')';
    }


    /**
     * The non-concatenated version of getArrayPlaceholders()
     *
     * @param array $input
     * @param string $prefix
     * @return array
     */
    protected static function getPlaceholders($input, $prefix = null)
    {
        $placeholders = [];
        foreach ($input as $i => $param) {
            $placeholders[] = isset($prefix) ? (':' . $prefix . '_' . $i) : '?';
        }
        return $placeholders;
    }


    /**
     * Flattens a list of parameters for an array.
     *
     * Used in conjunction with getArrayPlaceholders() - calling this with the
     * same input and prefix will generate params and placeholders that work
     * with each other.
     *
     * @param array  $input
     * @param string $prefix If not supplied, ? will be assumed as placeholder
     *                       for all values.
     *
     * @return array
     */
    public static function getArrayParameters($input, $prefix = null)
    {
        $params = array();
        foreach ($input as $i => $param) {
            if (isset($prefix)) {
                $params[$prefix . '_' . $i] = $param;
            } else {
                $params[] = $param;
            }
        }
        return $params;
    }



    /**
     * Return whether or not the given argument can be traversed
     *
     * @param mixed $mixed
     * @return bool
     */
    public static function canTraverse($mixed)
    {
        return is_array($mixed) || ($mixed instanceof Traversable && $mixed instanceof Countable);
    }


    /**
     * Return the target table
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }


    /**
     * Set the target table
     *
     * @param string $table
     * @return static
     */
    protected function setTable($table)
    {
        $this->table = $table;
        return $this;
    }


    /**
     * Return the predicates that have been stored up in this builder.
     * Convenience method to match expected signature of Mapper::getWherePart()
     *
     * @return array
     */
    public function getPredicates()
    {
        return [
            'where'  => $this->where,
            'params' => $this->params
        ];
    }


    /**
     * Return the number of conditions contained by this builder
     *
     * @return int
     */
    public function count()
    {
        return count($this->where);
    }

    /**
     * @return array
     */
    public function getWhere()
    {
        return $this->where;
    }


    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }


    /**
     * Just be careful using this: this is for if you need custom params in your joins
     *
     * @param string $param
     * @param mixed  $value
     * @return static
     */
    public function putParam($param, $value)
    {
        $this->params[$param] = $value;
        return $this;
    }

    /**
     * Find an alias for $param which isn't already in use in $this->params.
     *
     * @param string $param
     * @return string
     */
    public function getNonCollidingParamAlias($param)
    {
        $i = 0;
        while ($i < 10000) {
            $candidate = "{$param}_{$i}";
            if (!array_key_exists($candidate, $this->params)) {
                return $candidate;
            }
            $i++;
        }
        // Couldn't generate? Crash and burn
        return false;
    }

}
