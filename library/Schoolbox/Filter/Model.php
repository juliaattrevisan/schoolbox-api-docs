<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
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
     * Pages where this filter resides
     *
     * @var GenericCollection|Page\Model[]
     */
    private $pages;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pages = new GenericCollection(Page\Model::class);
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


    /**
     * @return GenericCollection|Page\Model[]
     */
    public function getPages()
    {
        return $this->pages;
    }


    /**
     * @param GenericCollection|Page\Model[] $pages
     * @return $this
     */
    public function setPages(GenericCollection $pages)
    {
        $this->pages->replaceWith($pages);
        return $this;
    }
}
