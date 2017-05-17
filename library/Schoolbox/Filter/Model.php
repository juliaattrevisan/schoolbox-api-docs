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
     * The mapper class of the entity this filter relates to,
     *
     * eg the 'weighting' assessment filter relates to the 'assessment' mapper
     *
     * @var string
     */
    private $entity;


    /**
     * File name of the class that contains the filter-type-specific logic
     *
     * @var string
     */
    private $class;


    /**
     * @return string
     * @todo Think of a better way to do this. Should be twig responsibility
     */
    public function getName()
    {
        $pathFragments = explode('\\', $this->class);
        return $pathFragments[count($pathFragments) - 2];
    }


    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getEntity()
    {
        return $this->entity;
    }


    /**
     * @param string $entity
     * @return $this
     */
    public function setEntity($entity)
    {
        $this->entity = $entity;
        return $this;
    }

    /**
     * @return string|IDefinition
     */
    public function getClass()
    {
        return $this->class;
    }


    /**
     * @param string $class
     * @return $this
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }
}
