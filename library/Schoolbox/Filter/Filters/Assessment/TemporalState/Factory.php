<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\User;
use Schoolbox\Filter;

/**
 * A factory for building 'TemporalState' filter objects
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
            $this->getTemporalStates()
        );
    }


    /**
     * @inheritdoc
     */
    protected function buildDefinitionArgs(Filter\Model $filter)
    {
        return array_merge(parent::buildDefinitionArgs($filter), [
            'temporalStates' => $this->getTemporalStates(),
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
        return array_merge(parent::buildKeyConverterArgs($filter), [
            'temporalStates' => $this->getTemporalStates(),
        ]);
    }


    /**
     * @return Collection|string[]
     */
    private function getTemporalStates()
    {
        return new Collection([
            Definition::KEY_UNPUBLISHED,
            Definition::KEY_UPCOMING,
            Definition::KEY_OPEN,
            Definition::KEY_OVERDUE,
            Definition::KEY_CLOSED,
        ]);
    }

}
