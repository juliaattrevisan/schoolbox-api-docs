<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * A factory for building 'WorkType' filter objects
 */
class Factory extends Filter\AbstractFactory
{

    /**
     * @var WorkType\Mapper
     */
    private $workTypeMapper;


    /**
     * @param Filter\Mapper                      $filterMapper
     * @param Filter\Page\Mapper                 $pageMapper
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param WorkType\Mapper                    $workTypeMapper
     */
    public function __construct(
        Filter\Mapper $filterMapper,
        Filter\Page\Mapper $pageMapper,
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        WorkType\Mapper $workTypeMapper
    ) {
        parent::__construct($filterMapper, $pageMapper, $optionMapper, $defaultMapper, $selectionMapper);
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
    protected function buildExtraComponentArgs(
        User $user,
        Filter\Page\Model $pageFilter,
        Filter\IDefinition $definition,
        GenericCollection $overrides = null,
        User $viewee = null
    ) {
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
