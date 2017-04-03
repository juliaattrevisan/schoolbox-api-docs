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


    /**
     * @param Model           $filter
     * @param Option\Mapper   $filterOptionMapper
     * @param Defaults\Mapper $filterDefaultMapper
     */
    public function __construct(Filter\Model $filter, Option\Mapper $filterOptionMapper, Defaults\Mapper $filterDefaultMapper)
    {
        $this->filter              = $filter;
        $this->filterOptionMapper  = $filterOptionMapper;
        $this->filterDefaultMapper = $filterDefaultMapper;
    }


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
     * @return Filter\Model
     */
    public function getFilter()
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
    public function getFilterDefaultMapper()
    {
        return $this->filterDefaultMapper;
    }
}
