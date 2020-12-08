<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge\CurriculumNode;

use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Curriculum\Node\Model as CurriculumNodeModel;
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
     * The user selections for this component are the same ones as were passed
     * via the URL.
     *
     * This is because it is likely there are too many curriculum nodes in an
     * instance to be able to load all of them before rendering this component.
     *
     * @param GenericCollection|Filter\Selection\Model[] $roleSelections
     * @return Filter\Selection\Model[]|GenericCollection
     */
    protected function buildUserSelections(GenericCollection $roleSelections)
    {
        $userSelections = parent::buildUserSelections($roleSelections);

        // Add the URL overrides and show them as user selections
        foreach (($this->getUrlOverrides() ?? []) as $key => $override) {
            if ($override instanceof Filter\Selection\Model) {
                $userSelections[$key] = $this->createSelection($key, $override->getSelected());
            }
        }

        return $userSelections;
    }


    /**
     * Load the selected nodes into the template, so that they may be rendered
     * in the selectize.
     *
     * @return array
     */
    public function toContext(): array
    {
        $context = parent::toContext();

        // Load selected nodes into the Twig template
        $context['selectedNodes'] = $this->curriculumNodeMapper
            ->findByColumn('id', $context['userSelectionIds'])
            ->map(function (CurriculumNodeModel $model) {
                // Load ancestors for each node, to maintain consistent
                // rendering with the selectize.
                // TODO consider if loading ancestors is really necessary
                return $this->curriculumNodeMapper->loadAncestors($model, 'parent');
            });

        return $context;
    }
}
