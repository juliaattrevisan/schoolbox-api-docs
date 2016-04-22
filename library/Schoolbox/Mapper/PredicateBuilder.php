<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Mapper;

use Countable;
use Database;
use Traversable;
use UnderflowException;

class PredicateBuilder
{

    /** @var Database */
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
     * @param Database $db
     * @param string   $table
     */
    public function __construct(Database $db, $table)
    {
        $this->setTable($table);
        $this->database = $db;
    }


    /**
     * @param array $columnList
     * @return self
     */
    public function addColumnList(array $columnList)
    {
        foreach ($columnList as $columnName => $columnValue) {
            $this->addPredicate($columnName, $columnValue);
        }

        return $this;
    }


    /**
     * @param string $columnName
     * @param mixed  $columnValue
     * @throws UnderflowException if an empty IN is attempted
     * @return self
     */
    public function addPredicate($columnName, $columnValue)
    {
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

        if (substr($columnName, 0, 1) == "!")
            $columnName = substr($columnName, 1);

        // If a field name starts with "!" character then we'll assume the query intention for the field is "not equal"
        if (substr($field, 0, 1) == "!") {
            $whereNot        = "NOT ";
            $whereComparison = "<>";
            $field           = substr($field, 1);

        } else {
            $whereNot        = "";
            $whereComparison = "=";
        }

        $escaped_name = $this->database->quoteIdentifier($table) . '.' . $this->database->quoteIdentifier($field);

        if (!isset($columnValue)) {
            $this->where[] = "$escaped_name IS {$whereNot}NULL";

        } elseif ($columnValue === true) {
            $this->where[] = "$escaped_name IS TRUE";

        } elseif ($columnValue === false) {
            $this->where[] = "$escaped_name IS FALSE";

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

                $this->where[] = "$escaped_name {$whereNot}IN {$this->getArrayPlaceholders($columnValue, $columnName)}";
                $this->params  = array_merge($this->params, $this->getArrayParameters($columnValue, $columnName));

            } else {
                // alias the param
                $a = $columnName . '_0';

                $this->where[]    = "$escaped_name $whereComparison :$a";
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
    protected function getArrayPlaceholders($input, $prefix = null)
    {
        $placeholders = array();
        foreach ($input as $i => $param) {
            $placeholders[] = isset($prefix) ? (':' . $prefix . '_' . $i) : '?';
        }
        return '(' . implode(',', $placeholders) . ')';
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
    protected function getArrayParameters($input, $prefix = null)
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
    protected function canTraverse($mixed)
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
    }

}
