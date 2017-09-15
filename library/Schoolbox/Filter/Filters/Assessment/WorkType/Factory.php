<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WorkType;

use Schoolbox\Collection;
use Schoolbox\Filter;
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
     * @param Filter\Page\Option\Mapper          $optionMapper
     * @param Filter\Page\Option\Defaults\Mapper $defaultMapper
     * @param Filter\Selection\Mapper            $selectionMapper
     * @param WorkType\Mapper                    $workTypeMapper
     */
    public function __construct(
        Filter\Page\Option\Mapper $optionMapper,
        Filter\Page\Option\Defaults\Mapper $defaultMapper,
        Filter\Selection\Mapper $selectionMapper,
        WorkType\Mapper $workTypeMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->workTypeMapper = $workTypeMapper;
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->getWorkTypes()
        );
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
        Collection $overrides,
        User $viewee = null
    ) {
        return [];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        $workTypeIds = $this->getWorkTypes()->getColumnValues('id');

        return array_merge(parent::buildKeyConverterArgs($filter), [
            'workTypes' => new Collection(array_combine($workTypeIds, $workTypeIds)),
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
