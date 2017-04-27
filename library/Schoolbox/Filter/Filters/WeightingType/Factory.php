<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterFactory;
use Schoolbox\Filter\IRunTimeOptions;
use Schoolbox\User;
use Schoolbox\Mapper;
use Schoolbox\Filter;

/**
 * A factory for building WeightingTypeFilter objects
 */
class Factory extends AbstractFilterFactory
{

    /**
     * @inheritdoc
     */
    public function buildDefinition(Filter\Model $filter)
    {
        return new Definition(
            $filter,
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultMapper(),
            $this->getWeightingTypes()
        );
    }


    /**
     * @return Collection|string[]
     */
    private function getWeightingTypes()
    {
        return new Collection([
            Definition::KEY_UNWEIGHTED,
            Definition::KEY_WEIGHTED,
        ]);
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function buildComponent(User $user, $page, IRunTimeOptions $runTimeOptions = null)
    {
        $filter          = $this->getFilterMapper()->findByClass(Definition::class);
        $definition      = $this->buildDefinition($filter);
        $savedSelections = $this->getSavedSelections($filter, $user, $page);

        return new Component($definition, $user, $page, $savedSelections, $this->getFilterSelectionMapper());
    }


    /**
     * @inheritdoc
     * @return KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter)
    {
        return new KeyConverter($filter, $this->getWeightingTypes());
    }

}
