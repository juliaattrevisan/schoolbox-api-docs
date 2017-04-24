<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\User;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * A factory for building 'WorkType' filter objects
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @var WorkType\Mapper
     */
    private $workTypeMapper;


    public function __construct(
        Filter\Mapper $filterMapper,
        Option\Mapper $optionMapper,
        Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        WorkType\Mapper $workTypeMapper
    ) {
        parent::__construct($filterMapper, $optionMapper, $defaultMapper, $selectionMapper);
        $this->workTypeMapper = $workTypeMapper;
    }


    /**
     * @inheritdoc
     */
    protected function buildDefinitionArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildDefinitionArgs($filter), [
            'workTypes' => $this->getWorkTypes(),
        ]);
    }


    /**
     * @inheritdoc
     */
    protected function buildExtraComponentArgs(User $user, $page, IRunTimeOptions $runTimeOptions = null, Filter\IFilterDefinition $definition)
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildKeyConverterArgs($filter), [
            'workTypes' => $this->getWorkTypes(),
        ]);
    }


    /**
     * Includes a NULL for when an assessment has no WorkType
     *
     * @return Collection|WorkType\Model[]
     */
    private function getWorkTypes()
    {
        $genericCollection = $this->workTypeMapper->findAll();
        $collection        = new Collection();
        $collection->offsetSet(Definition::KEY_NONE, null);
        foreach ($genericCollection as $workType) {
            $collection->offsetSet($workType->getId(), $workType);
        }
        return $collection;
    }

}
