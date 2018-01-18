<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Predicate;

use Alaress\Database\Quoter\IQuoter;
use Countable;
use Traversable;
use UnderflowException;

class Builder implements IBuilder
{
    private const OPERATOR_EQUALS     = '=';
    private const OPERATOR_NOT_EQUALS = '<>';
    private const OPERATOR_LIKE       = 'LIKE';
    private const OPERATOR_NOT_LIKE   = 'NOT LIKE';
    private const OPERATOR_LT         = '<';
    private const OPERATOR_LTE        = '<=';
    private const OPERATOR_GT         = '>';
    private const OPERATOR_GTE        = '>=';
    private const OPERATOR_INTERSECT  = 'AND';
    private const OPERATOR_UNION      = 'OR';

    /** @var IQuoter */
    protected $quoter;

    /** @var string */
    protected $table;

    /** @var string[] */
    protected $where = [];

    /** @var array */
    protected $params = [];

    /** @var string */
    protected $paramPrefix;

    /**
     * PredicateBuilder constructor.
     *
     * @param IQuoter $quoter
     * @param string  $table
     */
    public function __construct(IQuoter $quoter, string $table)
    {
        $this->table  = $table;
        $this->quoter = $quoter;
    }

    /**
     * Query the intersection (AND) of the conditions in the given predicate builder
     *
     * @param IBuilder $predicate
     * @return $this
     */
    public function all(IBuilder $predicate)
    {
        return $this->addSubPredicate(self::OPERATOR_INTERSECT, $predicate, false);
    }

    /**
     * Query the union (OR) of the conditions in the given predicate builder
     *
     * @param IBuilder $predicate
     * @return $this
     */
    public function any(IBuilder $predicate)
    {
        return $this->addSubPredicate(self::OPERATOR_UNION, $predicate, false);
    }

    /**
     * Query the negated intersection (NAND) of the conditions in the given predicate builder
     *
     * @param IBuilder $predicate
     * @return $this
     */
    public function notAll(IBuilder $predicate)
    {
        return $this->addSubPredicate(self::OPERATOR_INTERSECT, $predicate, true);
    }

    /**
     * Query the negated union (NOR) of the conditions in the given predicate builder
     *
     * @param IBuilder $predicate
     * @return $this
     */
    public function notAny(IBuilder $predicate)
    {
        return $this->addSubPredicate(self::OPERATOR_UNION, $predicate, true);
    }

    /**
     * Helper function for adding multiple predicates to this object.
     *
     * @param string   $glue
     * @param IBuilder $predicate
     * @param bool     $negate
     * @return $this
     */
    protected function addSubPredicate(string $glue, IBuilder $predicate, bool $negate)
    {
        // Extract this object's where and params
        $this->params  = array_merge($this->getParams(), $predicate->getParams());

        // Set the new where into action
        $this->where[] = ($negate ? 'NOT ' : '') . '('.implode(" $glue ", $predicate->getWhere()).')';

        return $this;
    }


    /**
     * Convenience method for adding directly from a Mapper column list
     *
     * @param array $columnList
     * @return self
     * @throws UnderflowException If column array is empty
     */
    public function addColumnList(array $columnList): self
    {
        foreach ($columnList as $columnName => $columnValue) {
            $split = preg_split('/\\s+/', $columnName, 2);
            if (\count($split) > 1) {
                $this->addPredicate($split[0], $columnValue, $split[1]);
            } else {
                $this->addPredicate($columnName, $columnValue);
            }
        }

        return $this;
    }


    /**
     * @param string $columnName
     * @return Builder
     */
    public function isNull($columnName): self
    {
        return $this->addPredicate($columnName, null);
    }


    /**
     * @param string $columnName
     * @return Builder
     */
    public function notNull($columnName): self
    {
        return $this->addPredicate($columnName, null, self::OPERATOR_NOT_EQUALS);
    }

    /**
     * @param string $columnName
     * @return Builder
     */
    public function isTrue($columnName): self
    {
        return $this->addPredicate($columnName, true);
    }

    /**
     * @param string $columnName
     * @return Builder
     */
    public function isFalse($columnName): self
    {
        return $this->addPredicate($columnName, false);
    }

    /**
     * @inheritdoc
     */
    public function equals($columnName, $columnValue)
    {
        return $this->addPredicate($columnName, $columnValue);
    }

    /**
     * @param string                    $columnName
     * @param string|int|string[]|int[] $columnValue
     * @return Builder
     * @throws UnderflowException If columnValue is an empty array
     */
    public function notEquals($columnName, $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_NOT_EQUALS);
    }

    /**
     * If $columnValue is an array, then the values are combined using 'OR'
     * eg, get student names where 'name' LIKE ['A%', 'B%'] would match both 'Andy' and 'Bob'.
     *
     * To get student names that match ('A%' AND '%Y') you need to chain two calls to like()
     *
     * @param string          $columnName
     * @param string|string[] $columnValue Used directly in LIKE statement.
     * @see \Database::escapeForLike for escaping user input.
     * @return Builder
     */
    public function like($columnName, $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_LIKE);
    }

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
     * @return Builder
     */
    public function notLike($columnName, $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_NOT_LIKE);
    }

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return Builder
     */
    public function lessThan($columnName, $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_LT);
    }

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return Builder
     */
    public function lessThanOrEqualTo($columnName, $columnValue): self
    {
        $builder = null;
        try{
            $builder = $this->addPredicate($columnName, $columnValue, self::OPERATOR_LTE);
        } catch (UnderflowException $e){
            // Impossible to throw, because operator is not 'IN'
        }
        return $builder;
    }


    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return Builder
     */
    public function greaterThan($columnName, $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_GT);
    }


    /**
     * @inheritdoc
     */
    public function greaterThanOrEqualTo($columnName, $columnValue)
    {
        $builder = null;
        try{
            $builder = $this->addPredicate($columnName, $columnValue, self::OPERATOR_GTE);
        } catch (UnderflowException $e){
            // Impossible to throw, because operator is not 'IN'
        }
        return $builder;
    }

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return Builder
     */
    public function notLessThan($columnName, $columnValue): self
    {
        return $this->greaterThanOrEqualTo($columnName, $columnValue);
    }

    /**
     * @param string     $columnName
     * @param int|string $columnValue  if string, must be datetime
     * @return Builder
     */
    public function notGreaterThan($columnName, $columnValue): self
    {
        return $this->lessThanOrEqualTo($columnName, $columnValue);
    }

    /**
     * Adds predicates for checking if this value is between two values.
     *
     * @param string $columnName
     * @param mixed  $from If null, this value is excluded.
     * @param mixed  $to   If null, this value is excluded.
     * @return Builder
     */
    public function between($columnName, $from, $to): self
    {
        // Either is null: ignore
        if (isset($from)) {
            $this->addPredicate($columnName, $from, self::OPERATOR_GTE);
        }
        if (isset($to)) {
            $this->addPredicate($columnName, $to, self::OPERATOR_LTE);
        }
        return $this;
    }

    /**
     * Adds predicates for checking if this value is between two dates.
     *
     * @param string               $columnName
     * @param \Alaress_Date_Format $from If null, this value is excluded.
     * @param \Alaress_Date_Format $to   If null, this value is excluded.
     * @param string               $compareFormat
     * @return Builder
     */
    public function betweenDates(
        $columnName,
        \Alaress_Date_Format $from = null,
        \Alaress_Date_Format $to = null,
        $compareFormat = \Alaress_Date_Format::DATABASE_FORMAT
    ): self {
        if (isset($from) && !$from->isNull()) {
            $this->addPredicate($columnName, $from->getUsingFormat($compareFormat), self::OPERATOR_GTE);
        }
        if (isset($to) && !$to->isNull()) {
            $this->addPredicate($columnName, $to->getUsingFormat($compareFormat), self::OPERATOR_LTE);
        }
        return $this;
    }

    /**
     * @param string $columnName
     * @param mixed  $columnValue
     * @param string $operator
     * @throws UnderflowException if an empty IN is attempted
     * @return self
     */
    protected function addPredicate($columnName, $columnValue, $operator = self::OPERATOR_EQUALS): self
    {
        // prepare operator modifiers
        $negate = self::OPERATOR_NOT_EQUALS == $operator || self::OPERATOR_NOT_LIKE == $operator;
        $fuzzy  = self::OPERATOR_LIKE == $operator || self::OPERATOR_NOT_LIKE == $operator;

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
        } else {
            $whereNot = "";
        }

        $escaped_name = $this->quoter->quoteIdentifier($table) . '.' . $this->quoter->quoteIdentifier($field);

        if (!isset($columnValue)) {
            $this->where[] = "$escaped_name IS {$whereNot}NULL";

        } elseif ($columnValue === true) {
            $this->where[] = "$escaped_name IS {$whereNot}TRUE";

        } elseif ($columnValue === false) {
            $this->where[] = "$escaped_name IS {$whereNot}FALSE";

        } else {
            // This method is smart enough to work with arrays and IN statements
            if (self::canTraverse($columnValue)) {

                if (count($columnValue) === 0) {
                    // bad, and not ignorable
                    throw new UnderflowException('List of column values is empty');
                }

                if ($fuzzy) {
                    // fuzzy match: use (LIKE :x OR LIKE :y), or (NOT LIKE :x AND NOT LIKE :y)
                    $likeParts = [];

                    foreach (self::getPlaceholders($columnValue, $columnName) as $option) {
                        $likeParts[] = "$escaped_name {$whereNot}LIKE $option";
                    }
                    $subOperator = $negate ? ' AND ' : ' OR ';
                    $this->where[] = '(' . implode($subOperator, $likeParts) . ')';

                } else {
                    // exact match: use IN
                    $placeHolders = self::getArrayPlaceholders($columnValue, $columnName);
                    $this->where[] = "$escaped_name {$whereNot}IN {$placeHolders}";
                }

                $this->params  = array_merge($this->params, self::getArrayParameters($columnValue, $columnName));

            } else {
                // alias the param
                $a = $this->getNonCollidingParamAlias($columnName);

                $this->where[] = "$escaped_name $operator :$a";
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
    public static function getArrayPlaceholders($input, $prefix = null): string
    {
        return '(' . implode(',', self::getPlaceholders($input, $prefix)) . ')';
    }

    /**
     * Gets an array of binding for placeholders- usually used for creating a
     * list of placeholders for an IN condition.
     *
     * @param array  $input
     * @param string $prefix If not supplied, ? will be used as the placeholder
     *                       for all values.
     *
     * @return string[] Something like:
     *
     *      [
     *          'placeholders' => ":id_0, :id_1, :id_2",
     *          'bindings' => [
     *              'id_0' => 123,
     *              'id_1' => 234,
     *              'id_3' => 345
     *          ]
     *      ]
     */
    public static function getArrayBindings($input, $prefix = null): array
    {
        $return = [];
        $return['placeholders'] = implode(",", self::getPlaceholders($input, $prefix));
        $return['bindings'] = [];

        $i = 0;
        foreach ($input as $key => $value) {
            $return['bindings'][isset($prefix) ? (':' . $prefix . '_' . self::escapePlaceholder($key)) : $i] = $value;
            $i++;
        }

        return $return;
    }


    /**
     * The non-concatenated version of getArrayPlaceholders()
     *
     * @param array $input
     * @param string $prefix
     * @return string[]
     */
    protected static function getPlaceholders($input, $prefix = null): array
    {
        $placeholders = [];
        foreach ($input as $i => $param) {
            $placeholders[] = isset($prefix) ? (':' . $prefix . '_' . self::escapePlaceholder($i)) : '?';
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
    public static function getArrayParameters($input, $prefix = null): array
    {
        $params = array();
        foreach ($input as $i => $param) {
            if (isset($prefix)) {
                $params[$prefix . '_' . self::escapePlaceholder($i)] = $param;
            } else {
                $params[] = $param;
            }
        }
        return $params;
    }


    /**
     * Strips dis-allowed characters from placeholders.
     *
     * @param string $placeholder
     * @return string
     */
    protected static function escapePlaceholder($placeholder): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $placeholder);
    }



    /**
     * Return whether or not the given argument can be traversed
     *
     * @param mixed $mixed
     * @return bool
     */
    public static function canTraverse($mixed): bool
    {
        return \is_array($mixed) || ($mixed instanceof Traversable && $mixed instanceof Countable);
    }


    /**
     * Return the target table
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }


    /**
     * Return the predicates that have been stored up in this builder.
     * Convenience method to match expected signature of Mapper::getWherePart()
     *
     * @return array
     */
    public function getPredicates(): array
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
    public function count(): int
    {
        return \count($this->where);
    }

    /**
     * @return string[]
     */
    public function getWhere(): array
    {
        return $this->where;
    }


    /**
     * @return array
     */
    public function getParams(): array
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
    public function putParam($param, $value): self
    {
        $this->params[$param] = $value;
        return $this;
    }

    /**
     * Set a parameter prefix, because multiple predicate builders in a single query are a thing
     *
     * @param $string
     * @return self|$this
     */
    public function setParamPrefix($string): self
    {
        $this->paramPrefix = $string;
        return $this;
    }

    /**
     * Find an alias for $param which isn't already in use in $this->params.
     *
     * @param string $param
     * @return string
     */
    public function getNonCollidingParamAlias($param): string
    {
        $i = 0;
        while ($i < 10000) {
            if ($this->paramPrefix != null) {
                $prefix = $this->paramPrefix . "_";
            } else {
                $prefix = '';
            }

            $candidate = "{$prefix}{$param}_{$i}";
            if (!array_key_exists($candidate, $this->params)) {
                return $candidate;
            }
            $i++;
        }
        // Couldn't generate? Crash and burn
        return false;
    }

}
