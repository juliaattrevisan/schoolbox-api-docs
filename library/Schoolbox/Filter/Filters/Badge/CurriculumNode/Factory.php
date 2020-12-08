<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Factory for the curriculum node filter.
 */
class Factory extends Filter\AbstractFactory
{
    /**
     * The mapper for retrieving curriculum nodes.
     *
     * Passed into the component so that it may render curriculum nodes.
     */
    private CurriculumNodeMapper $curriculumNodeMapper;


    /**
     * Constructor
     *
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param CurriculumNodeMapper        $curriculumNodeMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        CurriculumNodeMapper $curriculumNodeMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->curriculumNodeMapper = $curriculumNodeMapper;
    }


    /**
     * @inheritdoc
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition($filter, $this->getOptionMapper(), $this->getDefaultsMapper());
    }


    /**
     * @inheritdoc
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter($filter);
    }


    /**
     * This method builds a specialized component which contains the list of
     * curriculum nodes passed via `$overrides`.
     *
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
        GenericCollection $overrides
    ): IComponent {
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $this->curriculumNodeMapper
        );
    }
}
