<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;



use Schoolbox\Mapper\Traits\Model\Id;

class Model
{
    use Id;

    /**
     * File name of the class that contains the filter-type-specific logic
     *
     * @var string
     */
    private $class;


    /* Getters/Setters
     */

    /**
     * @return string|
     */
    public function getClass()
    {
        return $this->class;
    }


    /**
     * @param string $class
     * @return static
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }
}
