<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Pastoral\Type;

use Schoolbox\Filter\AbstractFactory;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\IKeyConverter;
use Schoolbox\Filter\Model;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral;
use Schoolbox\User;

/**
 * Factory for pastoral type/subtype filter.
 */
class Factory extends AbstractFactory
{
    /**
     * Constructor
     *
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param Pastoral\Type\Mapper        $pastoralTypeMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        private Pastoral\Type\Mapper $pastoralTypeMapper,
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
    }


    /**
     * @inheritdoc
     */
    public function buildDefinition(Model $filter): IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->pastoralTypeMapper->findAll(),
        );
    }

    /**
     * @inheritdoc
     */
    public function buildKeyConverter(Model $filter): IKeyConverter
    {
        return new KeyConverter($filter, $this->pastoralTypeMapper->findAllIds());
    }

    /**
     * @param IDefinition            $definition
     * @param User                   $user
     * @param Page\Model             $pageFilter
     * @param GenericCollection|null $selections
     * @param User|null              $viewee
     * @param array                  $routeArgs
     * @param GenericCollection      $overrides
     * @return IComponent
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides,
    ): IComponent {
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $this->pastoralTypeMapper->findAll(),
        );
    }
}
