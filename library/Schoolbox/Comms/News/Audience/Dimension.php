<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use function array_diff;
use function array_merge;
use function array_unique;
use function count;
use function implode;
use function in_array;
use function sprintf;

/**
 * A Dimension encapsulates an entity type and set of unique values.
 *
 * Examples:
 * * audience = Campus:X                   => 1 dimension
 * * audience = Campus:X,Y                 => 1 dimension
 * * audience = Campus:X,Y ∩ Role:A        => 2 dimension
 * * audience = Campus:X,Y ∩ Role:A ∩ SU:1 => 3 dimension
 *
 * Unlike the JS counterpart, here we don't apply the relationship modifier to the dimension.
 * (Instead, the relationship modifier is applied to the Brick.)
 */
class Dimension
{
    /**
     * A simple strategy entity type
     *
     * @var string
     */
    private $type;

    /**
     * Must be unique
     * @var string[]|null[]
     */
    private $values;


    /**
     * @param string      $type
     * @param string|null $value
     */
    public function __construct(string $type, ?string $value)
    {
        $this->type   = $type;
        $this->values = [$value];
    }


    /**
     * Returns DOF (degrees-of-freedom) between this and another Dimension
     *
     * @param Dimension $other
     * @return int
     */
    public function computeDof(self $other): int
    {
        // Different entity types?
        if ($this->type !== $other->type) {
            return 2;
        }
        // Different number of values?
        if (count($this->values) !== count($other->values)) {
            return 1;
        }
        // Different values?
        foreach ($other->values as $value) {
            if (!in_array($value, $this->values, true)) {
                return 1;
            }
        }
        return 0;
    }


    /**
     * Returns whether this dimension is a superset of another dimension.
     *
     * Returns true if dimensions are equal
     *
     * @param Dimension $other
     * @return bool
     */
    public function contains(self $other): bool
    {
        // Is set's type different?
        if ($this->type !== $other->type) {
            return false;
        }
        // Is other set bigger?
        if (count($other->values) > count($this->values)) {
            return false;
        }
        // Does other set have value not in this set?
        foreach ($other->values as $otherValue) {
            if (!in_array($otherValue, $this->values, true)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Try to add another dimension to this dimension via a suitable addition
     *
     * @param Dimension $other
     * @return bool
     */
    public function addDimension(self $other): bool
    {
        if ($this->type !== $other->type) {
            return false;
        }
        // No items in other that are not already in this?
        if (!count(array_diff($other->values, $this->values))) {
            return false;
        }
        $this->values = array_unique(array_merge($this->values, $other->values));
        return true;
    }


    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }


    /**
     * @return null[]|string[]
     */
    public function getValues(): ?array
    {
        return $this->values;
    }


    /**
     * Returns a string representation of this dimension, eg "folder:1,2,-"
     *
     * @return string
     */
    public function __toString()
    {
        $values = $this->values;
        foreach ($values as $idx => $value) {
            if ($value === null) {
                $values[$idx] = null;
            }
        }
        return sprintf("%s:%s", $this->type, implode(',', $this->values));
    }
}
