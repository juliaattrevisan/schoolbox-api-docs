<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

use UnexpectedValueException;

abstract class ValueBoundSpecification extends Leaf
{

    /** @var mixed */
    private $expectedValue;

    /** @var string */
    private $propertyName;


    /**
     * @param string $propertyName
     * @param mixed  $expectedValue
     */
    public function __construct(string $propertyName, $expectedValue)
    {
        $this->propertyName  = $propertyName;
        $this->expectedValue = $expectedValue;
    }


    /**
     * @param object $candidate
     * @return mixed
     * @throws UnexpectedValueException If property is not defined on $candidate
     */
    protected function getActualValue($candidate)
    {
        // We need to distinguish between NULL and undefined
        if (true !== \property_exists($candidate, $this->propertyName)) {
            $class = \get_class($candidate);
            throw new UnexpectedValueException("Property '{$this->propertyName}' is not defined on object of class '$class'");
        }

        // copy to local var, because $this will refer to $candidate object inside closure
        $property = $this->propertyName;

        // closure that returns the property value
        $get = function () use ($property) {
            return $this->{$property};
        };

        // call the closure on the object.
        return $get->call($candidate);
    }


    /**
     * @return mixed
     */
    protected function getExpectedValue()
    {
        return $this->expectedValue;
    }
}
