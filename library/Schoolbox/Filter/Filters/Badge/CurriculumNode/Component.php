<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\BaseComponent;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Component for rendering the curriculum node selector.
 */
class Component extends BaseComponent
{
    /**
     * The curriculum node mapper, used for converting URL selections into a
     * list of nodes for rendering.
     */
    private CurriculumNodeMapper $curriculumNodeMapper;


    /**
     * Constructor
     *
     * @param Filter\IDefinition      $definition
     * @param User                    $user
     * @param Filter\Page\Model       $pageFilter
     * @param GenericCollection|null  $userSelections
     * @param GenericCollection|null  $urlOverrides
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param CurriculumNodeMapper    $curriculumNodeMapper
     */
    public function __construct(
        Filter\IDefinition $definition,
        User $user,
        Filter\Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Filter\Selection\Mapper $filterSelectionMapper,
        CurriculumNodeMapper $curriculumNodeMapper
    ) {
        parent::__construct(
            $definition,
            $user,
            $pageFilter,
            $userSelections,
            $urlOverrides,
            $filterSelectionMapper,
        );
        $this->curriculumNodeMapper = $curriculumNodeMapper;
    }


    /**
     * This component has no role selections.
     *
     * @inheritdoc
     * @param GenericCollection|Filter\Page\Option\Model[] $abstractOptions
     * @throws \InvalidArgumentException If option key unrecognised
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        // Standard selections
        $availableSelections = parent::buildRoleSelections($abstractOptions);


        // Add URL overrides
        foreach ($this->getUrlOverrides() as $key => $override) {
            if ($override instanceof Filter\Selection\Model) {
                $availableSelections[$key] = $this->createSelection($key, $override->getSelected());
            }
        }

        return $availableSelections;
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        // Inject the URL overrides into the context
        $context = parent::toContext();
        $context['selectedNodes'] = $this->curriculumNodeMapper->findByColumn(
            'id',
            $context['roleSelectionIds']
        );

        return $context;
    }
}
