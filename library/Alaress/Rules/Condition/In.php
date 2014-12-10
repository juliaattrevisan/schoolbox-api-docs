<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Rules\Condition;

use Alaress\Rules\Condition as Super;


/**
 * @logic IF :input.$field (NOT) IN ( $validValues )
 *
 * Class InCondition
 */
abstract class In extends Super
{
    /** @var string */
    protected $field;

    /** @var array */
    protected $validValues;


    /**
     * @param string $field
     * @param array  $validValues
     * @param bool   $negate
     */
    public function __construct($field, $validValues, $negate = false)
    {
        $this->field       = (string)$field;
        $this->validValues = is_array($validValues) ? $validValues : array($validValues);

        parent::__construct(array($this, 'assertIn'), $negate);
    }


    /**
     * @param mixed $input
     * @return bool
     */
    public abstract function assertIn($input);


    /**
     * @return array
     */
    public function getValidValues()
    {
        return $this->validValues;
    }


    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        return sprintf("IF :input.%s %sIN (%s)", $this->field, $this->isNegated() ? 'NOT ' : '', implode(', ', $this->getValidValues()));
    }

}
