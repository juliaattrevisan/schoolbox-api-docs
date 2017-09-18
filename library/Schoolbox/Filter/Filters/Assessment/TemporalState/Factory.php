<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\Assessment\TemporalState;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFactory;
use Schoolbox\GenericCollection;
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
        return new KeyConverter($filter, $this->getTemporalStates());
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
