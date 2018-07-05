<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Specification;

use UnexpectedValueException;

abstract class ValueBoundSpecification extends Leaf
{

    /** @var mixed */
    private $value;

    /** @var string */
    private $property;


    /**
     * @param string $property
     * @param        $value
     */
    public function __construct(string $property, $value)
    {
        $this->property = $property;
        $this->value    = $value;
    }


    /**
     * @param object $candidate
     * @return mixed
     * @throws UnexpectedValueException If property is not defined on $candidate
     */
    protected function getPropertyValue($candidate)
    {
        // We need to distinguish between NULL and undefined
        if (true !== \property_exists($candidate, $this->property)) {
            $class = \get_class($candidate);
            throw new UnexpectedValueException("Property '{$this->property}' is not defined on object of class '$class'");
        }

        // copy to local var, because $this will refer to $candidate object inside closure
        $property = $this->property;

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
    protected function getValue()
    {
        return $this->value;
    }
}
