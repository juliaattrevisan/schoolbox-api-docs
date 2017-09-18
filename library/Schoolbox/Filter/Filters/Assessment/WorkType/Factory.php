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
     * @param Filter\IDefinition     $definition
     * @param User                   $user
     * @param Filter\Page\Model      $pageFilter
     * @param GenericCollection      $selections
     * @param null|User              $viewee
     * @param null|GenericCollection $overrides
     * @return Filter\IComponent|Component
     */
    public function buildComponent(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        GenericCollection $selections,
        User $viewee = null,
        GenericCollection $overrides = null
    ): Filter\IComponent {
        return new Component($definition, $user, $pageFilter, $overrides ?: $selections, $this->getSelectionMapper());
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        $workTypeIds = $this->getWorkTypes()->getColumnValues('id');
        return new KeyConverter($filter, new Collection(array_combine($workTypeIds, $workTypeIds)));
    }


    /**
     * Includes a NULL for when an assessment has no WorkType
     *
     * @return Collection|WorkType\Model[]|null[]
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
