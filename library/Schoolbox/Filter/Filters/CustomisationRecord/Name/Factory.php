<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\Name;

use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\IKeyConverter;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Factory for the customisation record student name filter.
 */
class Factory extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function buildDefinition(Model $filter): IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper()
        );
    }

    /**
     * @inheritdoc
     */
    public function buildKeyConverter(Model $filter): IKeyConverter
    {
        return new KeyConverter($filter);
    }

    /**
     * @inheritdoc
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): IComponent {
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper()
        );
    }
}
