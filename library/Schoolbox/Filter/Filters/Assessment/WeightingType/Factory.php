<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\WeightingType;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Filter;

/**
 * A factory for building 'WeightingType' filter objects
 */
class Factory extends AbstractFactory
{

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
    protected function buildExtraComponentArgs(User $user, $page, Filter\IDefinition $definition, GenericCollection $overrides = null, User $viewee = null)
    {
        return [];
    }


    /**
     * @inheritdoc
     */
    protected function buildKeyConverterArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildKeyConverterArgs($filter), [
            'weightingTypes' => $this->getWeightingTypes(),
        ]);
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
