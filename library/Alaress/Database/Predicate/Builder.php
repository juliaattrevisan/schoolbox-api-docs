<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Predicate;

use Alaress\Database\Quoter\IQuoter;
use Traversable;
use Alaress_Date_Format;
use UnderflowException;
use TypeError;

class Builder implements IBuilder
{
    private const OPERATOR_EQUALS     = '=';
    private const OPERATOR_NOT_EQUALS = '<>';
    private const OPERATOR_LIKE       = 'LIKE';
    private const OPERATOR_NOT_LIKE   = 'NOT LIKE';
    private const OPERATOR_REGEX      = 'REGEXP';
    private const OPERATOR_NOT_REGEX  = 'NOT REGEXP';
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
     * @inheritdoc
     * @return static
     */
    public function emptyClone(): IBuilder
    {
        return new static($this->quoter, $this->table);
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
        $where = ($negate ? 'NOT ' : '') . '(' . implode(" $glue ", $predicate->getWhere()) . ')';

        // Test for collisions if inner and outer PB contain same placeholder keys
        foreach ($predicate->getParams() as $placeholder => $param) {
            // Does this cause a collision?
            if (array_key_exists($placeholder, $this->params)) {
                $uniquePlaceholder                = $this->getNonCollidingParamAlias($placeholder);
                $where                            = preg_replace("/({$placeholder})([,)\ ]+)/", "$uniquePlaceholder$2", $where);
                $this->params[$uniquePlaceholder] = $param;
            } else {
                $this->params[$placeholder] = $param;
            }
        }
        // Set the new where into action
        $this->where[] = $where;
        return $this;
    }


    /**
     * Convenience method for adding directly from a Mapper column list
     *
     * @param array $columnList
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     * @throws UnderflowException If an empty traversable is provided
     */
    public function addColumnList(array $columnList): self
    {
        foreach ($columnList as $columnName => $columnValue) {
            if (\is_iterable($columnValue)) {
                try {
                    $this->in($columnName, $columnValue);
                } catch (TypeError $e) {
                    // impossible to throw as we have checked type is traversable
                }
            } else {
                // May be null, bool, or scalar
                $this->addPredicate($columnName, $columnValue);
            }
        }
        return $this;
    }


    /**
     * @param string|ISqlFunc $columnName
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function isNull($columnName): self
    {
        return $this->addPredicate($columnName, null);
    }


    /**
     * @param string $columnName
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function notNull($columnName): self
    {
        return $this->addPredicate($columnName, null, self::OPERATOR_NOT_EQUALS);
    }

    /**
     * @param string|ISqlFunc $columnName
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function isTrue($columnName): self
    {
        return $this->addPredicate($columnName, true);
    }

    /**
     * @param string|ISqlFunc $columnName
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function isFalse($columnName): self
    {
        return $this->addPredicate($columnName, false);
    }


    /**
     * @param string|ISqlFunc                      $columnName
     * @param string|int|float|Alaress_Date_Format $columnValue
     * @param string                               $operator
     * @return Builder
     * @throws InvalidColumnException
     * @throws TypeError If $columnValue is of invalid type. Use isNull() for matching NULL
     */
    private function addScalarPredicate($columnName, $columnValue, string $operator): self
    {
        if (\is_scalar($columnValue) || ($columnValue instanceof Alaress_Date_Format && !$columnValue->isNull())) {
            return $this->addPredicate($columnName, $columnValue, $operator);
        }
        throw new TypeError('Invalid columnValue type. Expected: [string|int|float|ADF]. Actual: ' . gettype($columnValue));
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param iterable        $columnValues
     * @param string          $operator
     * @throws InvalidColumnException
     * @throws UnderflowException         If iterable is empty
     * @return $this
     */
    private function addIterablePredicate($columnName, iterable $columnValues, string $operator)
    {
        if (0 === \count($columnValues)) {
            // While this logically equates to $this->>where[] = 'TRUE'|'FALSE', that would still trigger
            // a potentially pointless round-trip to the DB. The caller should handle this case in code instead.
            throw new UnderflowException('List of column values is empty');
        }
        return $this->addPredicate($columnName, $columnValues, $operator);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param string|int      $columnValue Ideally we would type-declare as 'scalar', but not possible.
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     * @throws TypeError If column value is not a scalar (use isNull() if testing for NULLs)
     */
    public function equals($columnName, $columnValue): self
    {
        return $this->addScalarPredicate($columnName, $columnValue, self::OPERATOR_EQUALS);
    }


    /**
     * @param string $columnName
     * @param string $regex
     * @return $this
     * @throws InvalidColumnException
     */
    public function regex(string $columnName, string $regex): self
    {
        return $this->addScalarPredicate($columnName, $regex, self::OPERATOR_REGEX);
    }


    /**
     * @param string $columnName
     * @param string $regex
     * @return $this
     * @throws InvalidColumnException
     */
    public function notRegex(string $columnName, string $regex): self
    {
        return $this->addScalarPredicate($columnName, $regex, self::OPERATOR_NOT_REGEX);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param string|int      $columnValue
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. Use isNull() for matching NULL
     * @throws InvalidColumnException If unable to parse column name
     */
    public function notEquals($columnName, $columnValue): self
    {
        return $this->addScalarPredicate($columnName, $columnValue, self::OPERATOR_NOT_EQUALS);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param iterable        $columnValues
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     * @throws UnderflowException If an empty traversable is provided
     */
    public function in($columnName, iterable $columnValues): self
    {
        return $this->addIterablePredicate($columnName, $columnValues, self::OPERATOR_EQUALS);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param iterable        $columnValues
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     * @throws UnderflowException If an empty traversable is provided
     */
    public function notIn($columnName, iterable $columnValues): self
    {
        return $this->addIterablePredicate($columnName, $columnValues, self::OPERATOR_NOT_EQUALS);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param string          $columnValue Used directly in LIKE statement.
     * @see \Database::escapeForLike for escaping user input.
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function like($columnName, string $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_LIKE);
    }

    /**
     * Column values are combined using 'OR'
     * eg, get student names where 'name' LIKE ['A%', 'B%'] would match both 'Andy' and 'Bob'.
     *
     * To get student names that match ('A%' AND '%Y') you need to chain two calls to like()
     *
     * @param string|ISqlFunc $columnName
     * @param string[]        $columnValues Used directly in LIKE statement.
     * @see \Database::escapeForLike for escaping user input.
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function likeAny($columnName, array $columnValues): self
    {
        $subBuilder = $this->emptyClone();
        foreach ($columnValues as $columnValue) {
            $subBuilder->like($columnName, $columnValue);
        }
        return $this->any($subBuilder);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param string          $columnValue Used directly in NOT LIKE statement.
     * @see \Database::escapeForLike for escaping user input.
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function notLike($columnName, string $columnValue): self
    {
        return $this->addPredicate($columnName, $columnValue, self::OPERATOR_NOT_LIKE);
    }


    /**
     * If $columnValue is array, then $columnName must be lessThan all values
     *
     * @param string|ISqlFunc $columnName
     * @param int|string      $columnValue if string, must be datetime
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. Use isNull() for matching NULL
     * @throws InvalidColumnException If unable to parse column name
     */
    public function lessThan($columnName, $columnValue): self
    {
        return $this->addScalarPredicate($columnName, $columnValue, self::OPERATOR_LT);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param int|string      $columnValue if string, must be datetime
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. (Use isNull() for matching NULL)
     * @throws InvalidColumnException If unable to parse column name
     */
    public function lessThanOrEqualTo($columnName, $columnValue): self
    {
        return $this->addScalarPredicate($columnName, $columnValue, self::OPERATOR_LTE);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param int|string      $columnValue if string, must be datetime
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. (Use isNull() for matching NULL)
     * @throws InvalidColumnException If unable to parse column name
     */
    public function greaterThan($columnName, $columnValue): self
    {
        return $this->addScalarPredicate($columnName, $columnValue, self::OPERATOR_GT);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param int|string      $columnValue
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. (Use isNull() for matching NULL)
     * @throws InvalidColumnException If unable to parse column name
     */
    public function greaterThanOrEqualTo($columnName, $columnValue): self
    {
        return $this->addScalarPredicate($columnName, $columnValue, self::OPERATOR_GTE);
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param int|string      $columnValue if string, must be datetime
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. (Use isNull() for matching NULL)
     * @throws InvalidColumnException If unable to parse column name
     */
    public function notLessThan($columnName, $columnValue): self
    {
        return $this->greaterThanOrEqualTo($columnName, $columnValue);
    }

    /**
     * @param string|ISqlFunc $columnName
     * @param int|string      $columnValue if string, must be datetime
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. (Use isNull() for matching NULL)
     * @throws InvalidColumnException If unable to parse column name
     */
    public function notGreaterThan($columnName, $columnValue): self
    {
        return $this->lessThanOrEqualTo($columnName, $columnValue);
    }


    /**
     * Adds predicates for checking if this value is between two values.
     *
     * @param string|ISqlFunc  $columnName
     * @param null|string|int  $from If null, this value is excluded.
     * @param null|string|int  $to   If null, this value is excluded.
     * @return $this
     * @throws TypeError If $columnValue is of invalid type. (Use isNull() for matching NULL)
     * @throws InvalidColumnException If unable to parse column name
     */
    public function between($columnName, $from, $to): self
    {
        // If both are set we need to use a sub-builder. Otherwise we may combine predicates
        // with something other than AND and get unexpected results
        if (null !== $from && null !== $to) {
            $subBuilder = $this->emptyClone()
                               ->greaterThanOrEqualTo($columnName, $from)
                               ->lessThanOrEqualTo($columnName, $to);
            return $this->all($subBuilder);
        }

        // Else we only have a single clause and no such issue can occur
        if (isset($from)) {
            $this->greaterThanOrEqualTo($columnName, $from);
        }
        if (isset($to)) {
            $this->lessThanOrEqualTo($columnName, $to);
        }
        return $this;
    }

    /**
     * Adds predicates for checking if this value is between two dates.
     *
     * @param string|ISqlFunc      $columnName
     * @param Alaress_Date_Format  $from If null, this value is excluded.
     * @param Alaress_Date_Format  $to   If null, this value is excluded.
     * @param string               $compareFormat
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    public function betweenDates(
        $columnName,
        Alaress_Date_Format $from = null,
        Alaress_Date_Format $to = null,
        $compareFormat = Alaress_Date_Format::DATABASE_FORMAT
    ): self {
        $fromString = $toString = null;
        if (isset($from) && !$from->isNull()) {
            $fromString = $from->getUsingFormat($compareFormat);
        }
        if (isset($to) && !$to->isNull()) {
            $toString = $to->getUsingFormat($compareFormat);
        }
        try {
            return $this->between($columnName, $fromString, $toString);
        } catch (TypeError $e) {
            // Impossible to throw as we are passing null or string
            return null;
        }
    }


    /**
     * @param string|ISqlFunc $columnName
     * @param mixed           $columnValue
     * @param string          $operator
     * @return $this
     * @throws InvalidColumnException If unable to parse column name
     */
    protected function addPredicate($columnName, $columnValue, string $operator = self::OPERATOR_EQUALS): self
    {
        // prepare operator modifiers
        $negate   = self::OPERATOR_NOT_EQUALS === $operator || self::OPERATOR_NOT_LIKE === $operator;
        $whereNot = $negate ? 'NOT ' : '';

        // prepare left operand
        if ($columnName instanceof ISqlFunc) {
            $escapedOperand = $columnName->getEscapedOperand($this->quoter, $this->table);
            $param          = $columnName->getParam();
        } else {
            $escapedOperand = $this->quoter->getEscapedOperand($this->table, $columnName);
            $param = str_replace('.', '_', $columnName);
        }

        if (!isset($columnValue)) {
            $this->where[] = "$escapedOperand IS {$whereNot}NULL";
        } elseif ($columnValue === true) {
            $this->where[] = "$escapedOperand IS {$whereNot}TRUE";
        } elseif ($columnValue === false) {
            $this->where[] = "$escapedOperand IS {$whereNot}FALSE";
        } elseif (\is_iterable($columnValue)) {
            $param = $this->paramPrefix ? "{$this->paramPrefix}_{$param}" : $param;
            $placeHolders  = self::getArrayPlaceholders($columnValue, $param);
            $this->where[] = "$escapedOperand {$whereNot}IN {$placeHolders}";
            $this->params  = array_merge($this->params, self::getArrayParameters($columnValue, $param));
        } else {
            // alias the param
            $a             = $this->getNonCollidingParamAlias($param);
            $this->where[] = "$escapedOperand $operator :$a";
            $this->putParam($a, $columnValue);
        }

        return $this;
    }


    /**
     * Gets a string of placeholders for an array - usually used for creating a
     * list of placeholders for an IN condition.
     *
     * @param array|Traversable $input
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
     * Returns strongly-typed bindings, suitable for use in a IN clause.
     *
     * Does not support positional binding (?) style. Enforces named bindings.
     *
     * @param array  $input
     * @param string $prefix
     * @return Bindings
     */
    public static function getBindings(array $input, string $prefix): Bindings
    {
        $arrayBindings = self::getArrayBindings($input, $prefix);
        return new Bindings($arrayBindings['placeholders'], $arrayBindings['bindings']);
    }


    /**
     * The non-concatenated version of getArrayPlaceholders()
     *
     * @param array|Traversable $input
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
        return \is_iterable($mixed);
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
     * @inheritdoc
     */
    public function putParam(string $param, $value): IBuilder
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
