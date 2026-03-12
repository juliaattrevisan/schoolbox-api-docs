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
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Factory for the customisation record student name filter.
 */
class Factory extends AbstractFactory
{
    /**
     * Constructor
     *
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param UserMapper                  $userMapper
     * @param RosettaStone                $rosettaStone
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        private UserMapper $userMapper,
        private RosettaStone $rosettaStone,
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
            $this->getSelectionMapper(),
            $this->userMapper,
            $this->rosettaStone,
        );
    }
}
