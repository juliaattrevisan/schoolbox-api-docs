<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IFilterDefinition;
use Schoolbox\Filter\IRunTimeOptions;
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
    public function buildDefinition()
    {
        return (new Definition($this->getFilter()))
            ->setWorkTypes($this->getWorkTypes());
    }


    /**
     * Build a filter, ready to inject into a filter panel
     *
     * @param null|IRunTimeOptions $runTimeOptions
     * @return IFilterDefinition
     */
    public function buildComponent(IRunTimeOptions $runTimeOptions = null)
    {
        return (new Definition($this->getFilter()))
            ->setWorkTypes($this->getWorkTypes());
    }


    /**
     * Build a filter definition that is able to convert keys back to elements
     *
     * @return IFilterDefinition
     */
    public function buildKeyConverter()
    {
        // TODO: Implement buildForConvertingKeyToElement() method.
    }
}
