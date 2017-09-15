<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\User;
use Schoolbox\Filter;

/**
 * A factory for building 'WeightingType' filter objects
 */
class Factory extends AbstractFactory
{

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
            $this->getWeightingTypes()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter, $this->getWeightingTypes());
    }


    /**
     * @inheritdoc
     */
    protected function buildDefinitionArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildDefinitionArgs($filter), [
            'weightingTypes' => $this->getWeightingTypes(),
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
     * @return Collection|string[]
     */
    private function getWeightingTypes()
    {
        return new Collection([
            Definition::KEY_UNWEIGHTED,
            Definition::KEY_WEIGHTED,
        ]);
    }

}
