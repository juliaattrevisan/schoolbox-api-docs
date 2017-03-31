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
use Schoolbox\GenericCollection;

/**
 * Provides common filter definition behaviour
 */
abstract class AbstractFilterDefinition implements IFilterDefinition
{

    /** @var Filter\Model */
    private $filter;

    /** @var Option\Mapper */
    private $filterOptionMapper;

    /** @var Defaults\Mapper */
    private $filterDefaultMapper;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * @return string
     */
    protected abstract function getTemplateFile();

    /**
     * Each filter definition must know how to sort options
     *
     * @param GenericCollection|Option\Model[] $options
     * @return GenericCollection
     */
    protected abstract function sortFilterOptions(GenericCollection $options);


    /**
     * @inheritdoc
     */
    public function getAdminTemplate()
    {
        return "@filters/admin/{$this->getTemplateFile()}";
    }


    /**
     * @inheritdoc
     */
    public function getOptionsTemplate()
    {
        return "@filters/options/{$this->getTemplateFile()}";
    }


    /**
     * @inheritdoc
     */
    public function getDefaultsTemplate()
    {
        return "@filters/defaults/{$this->getTemplateFile()}";
    }


    /**
     * @inheritdoc
     */
    public function getPanelTemplate()
    {
        return "@filters/panel/{$this->getTemplateFile()}";
    }

    /**
     * @return Filter\Model
     */
    public function getFilter()
    {
        return $this->filter;
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


    /**
     * @return Option\Mapper
     */
    protected function getFilterOptionMapper()
    {
        return $this->filterOptionMapper;
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
     * @return Defaults\Mapper
     */
    protected function getFilterDefaultMapper()
    {
        return $this->filterDefaultMapper;
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
     * @return Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }


    /**
     * @param Selection\Mapper $filterSelectionMapper
     * @return $this
     */
    public function setFilterSelectionMapper(Selection\Mapper $filterSelectionMapper)
    {
        $this->filterSelectionMapper = $filterSelectionMapper;
        return $this;
    }

}
