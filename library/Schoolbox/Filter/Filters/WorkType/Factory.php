<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\Model as Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * A factory for building TermFilters
 */
class Factory extends AbstractFilterFactory
{
    /** @var WorkType\Mapper */
    private $workTypeMapper;

    /**
     * @param WorkType\Mapper $workTypeMapper
     */
    public function __construct(WorkType\Mapper $workTypeMapper)
    {
        $this->workTypeMapper = $workTypeMapper;
    }


    /**
     * @return GenericCollection|WorkType\Model[]
     */
    private function getWorkTypes()
    {
        return $this->workTypeMapper->findAll(['order' => ['name' => 'ASC']])->indexBy('id');
    }


    /**
     * Build a filter definition, ready to use in the admin pages
     *
     * @return IFilterDefinition
     */
    public function buildForAdminPages()
    {
        return (new Definition($this->getFilter()))
            ->setWorkTypes($this->getWorkTypes());
    }


    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param mixed[] $params - Factory must validate and convert each to calls to setters TODO Fa
     * @return IFilterDefinition
     */
    public function buildForFilterPanel(array $params = [])
    {
        return (new Definition($this->getFilter()))
            ->setWorkTypes($this->getWorkTypes());
    }
}
