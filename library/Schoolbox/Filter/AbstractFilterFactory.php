<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Option\Selection;


abstract class AbstractFilterFactory implements IFilterFactory
{

    /** @var Filter\Model */
    private $filter;

    /** @var Option\Mapper */
    private $filterOptionMapper;

    /** @var Defaults\Mapper */
    private $filterDefaultMapper;

    /** @var \Schoolbox\Filter\Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * Injects the common dependencies shared by all definition filters
     * @param AbstractFilterDefinition $definition
     */
    protected function setBaseDependencies(AbstractFilterDefinition $definition)
    {
        $definition
            ->setFilter($this->filter)
            ->setFilterOptionMapper($this->filterOptionMapper)
            ->setFilterDefaultMapper($this->filterDefaultMapper)
            ->setFilterSelectionMapper($this->filterSelectionMapper);
    }


    /**
     * @param Filter\Model $filter
     * @return $this
     */
    public function setFilter(Filter\Model $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    public function getFilterId()
    {
        return $this->filter->getId();
    }


    /**
     * @param Option\Mapper $filterOptionMapper
     * @return $this
     */
    public function setFilterOptionMapper(Option\Mapper $filterOptionMapper)
    {
        $this->filterOptionMapper = $filterOptionMapper;
        return $this;
    }


    /**
     * @param Defaults\Mapper $filterDefaultMapper
     * @return $this
     */
    public function setFilterDefaultMapper(Defaults\Mapper $filterDefaultMapper)
    {
        $this->filterDefaultMapper = $filterDefaultMapper;
        return $this;
    }


    /**
     * @param \Schoolbox\Filter\Selection\Mapper $filterSelectionMapper
     * @return $this
     */
    public function setFilterSelectionMapper(\Schoolbox\Filter\Selection\Mapper $filterSelectionMapper)
    {
        $this->filterSelectionMapper = $filterSelectionMapper;
        return $this;
    }


    /**
     * @return Filter\Model
     */
    protected function getFilter()
    {
        return $this->filter;
    }


    /**
     * @return Option\Mapper
     */
    protected function getFilterOptionMapper()
    {
        return $this->filterOptionMapper;
    }


    /**
     * @return Defaults\Mapper
     */
    protected function getFilterDefaultMapper()
    {
        return $this->filterDefaultMapper;
    }


    /**
     * @return \Schoolbox\Filter\Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }
}
