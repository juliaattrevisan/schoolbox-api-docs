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
use Schoolbox\Mapper;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\WorkType;


/**
 * A factory for building WorkTypeFilter objects
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
    public function buildDefinition(Filter\Model $filter)
    {
        return new Definition(
            $filter,
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultMapper(),
            $this->getWorkTypes()
        );
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function buildComponent(User $user, $page, IRunTimeOptions $runTimeOptions = null)
    {
        $filter          = $this->getFilterMapper()->findForClass(Definition::class);
        $definition      = $this->buildDefinition($filter);
        $savedSelections = $this->getSavedSelections($filter, $user, $page);

        return new Component($definition, $user, $page, $savedSelections, $this->getFilterSelectionMapper());
    }


    /**
     * Returns a key converter, able to convert between keys (workType.id) and the entities they represent (WorkTypes)
     *
     * @return KeyConverter
     */
    public function buildKeyConverter()
    {
        $filter = $this->getFilterMapper()->findForClass(Definition::class);
        return new KeyConverter($filter, $this->getWorkTypes());
    }


    /**
     * Includes a NULL for when an assessment has no WorkType
     *
     * @return Collection|WorkType\Model[]
     */
    protected function getWorkTypes()
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
