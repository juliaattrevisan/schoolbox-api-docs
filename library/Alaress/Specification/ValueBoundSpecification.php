<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Alaress\Specification;

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
        $this->value = $value;
    }


    /**
     * @param $candidate
     * @return mixed
     */
    protected function getPropertyValue($candidate)
    {
        $getMethod = 'get' . \ucfirst($this->property);
        return $candidate->{$getMethod}();
    }

    /**
     * @return mixed
     */
    protected function getValue()
    {
        return $this->value;
    }
}
