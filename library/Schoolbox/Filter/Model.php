<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\Filter\Definition\IFilterDefinition;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Filter\Option\Model as FilterOption;

class Model
{
    use Id;

    /**
     * File name of the class that contains the filter-type-specific logic
     *
     * @var string
     */
    private $class;

    /**
     * An instance of the filter-type-specific logic
     *
     * @var IFilterDefinition
     */
    private $definition;


    /**
     * Ensure the filter implementation logic is loaded. Returns false if it was already loaded.
     * @return bool
     */
    public function loadClass()
    {
        if (null === $this->definition){
            $this->definition = new $this->class($this);
            return true;
        }
        return false;
    }



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
