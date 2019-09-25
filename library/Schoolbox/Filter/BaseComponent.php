<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Common logic for Filter Components
 */
class BaseComponent implements IComponent
{

    /** @var IDefinition */
    private $definition;

    /** @var User */
    private $user;

    /** @var Page\Model */
    private $pageFilter;

    /**
     * Will be NULL if user does not have any saved settings for this filter
     *
     * @var null|GenericCollection|Selection\Model[]
     */
    private $userSelections;

    /**
     * Will be NULL if no overrides are present in URL for this filter
     *
     * @var null|GenericCollection|Selection\Model[]
     */
    private $urlOverrides;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * @param IDefinition                              $definition
     * @param User                                     $user
     * @param Page\Model                               $pageFilter
     * @param null|GenericCollection|Selection\Model[] $userSelections
     * @param null|GenericCollection|Selection\Model[] $urlOverrides
     * @param Selection\Mapper                         $filterSelectionMapper
     * @throws \InvalidArgumentException If neither $userSelections or $urlOverrides are supplied
     */
    public function __construct(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $userSelections,
        ?GenericCollection $urlOverrides,
        Selection\Mapper $filterSelectionMapper
    ) {
        $this->definition            = $definition;
        $this->user                  = $user;
        $this->pageFilter            = $pageFilter;
        $this->userSelections        = $userSelections;
        $this->urlOverrides          = $urlOverrides;
        $this->filterSelectionMapper = $filterSelectionMapper;
    }


    /**
     * @inheritdoc
     */
    public function getTemplate(): string
    {
        return $this->definition::getPanelComponentTemplate();
    }


    /**
     * @inheritdoc
     */
    public function toContext()
    {
        $abstractOptions = $this->pageFilter->getOptions();

        // Role-selections === 'hard-coded' selections
        $roleSelections = $this->buildRoleSelections($abstractOptions);

        // User-selections. Don't build if not user-configurable
        $userSelections = $this->pageFilter->getUserConfigurable()
            ? $this->buildUserSelections($roleSelections)
            : $this->filterSelectionMapper->createCollection();

        // Output IDs only for role/user selections, to make it easier for
        // selectize elements to reset back to a previous state
        return [
            'isUserConfigurable' => $this->pageFilter->getUserConfigurable(),
            'hasOptions'         => (bool)$roleSelections->count(),
            'definition'         => $this->definition,
            'roleSelections'     => $roleSelections,
            'userSelections'     => $userSelections,
            'roleSelectionIds'   => $roleSelections->filterBy('selected', true)->keys(),
            'userSelectionIds'   => $userSelections->filterBy('selected', true)->keys(),
        ];
    }


    /**
     * Generate a list of concrete options, selected as per user's selections
     *
     * @param GenericCollection|Selection\Model[] $roleSelections
     * @return Selection\Model[]|GenericCollection
     */
    protected function buildUserSelections(GenericCollection $roleSelections)
    {
        $userSelections = $roleSelections->emptyClone();

        /* Do we have URL overrides present?
         * If so, all user-selections are deselected unless explicitly specified to be selected.
         * Else we overlay the role-selections with any saved user selections.
         */

        // Overlay user-specific selection settings
        foreach ($roleSelections as $key => $roleSelection) {
            $userSelection = clone $roleSelection;
            if ($this->urlOverrides) {
                // Select option only if explicitly declared by URL overrides
                $userSelection->setSelected(
                    $this->urlOverrides->offsetExists($key) && $this->urlOverrides[$key]->getSelected()
                );
            } elseif ($this->userSelections) {
                // fall back to any saved user selections
                if ($this->userSelections->offsetExists($key)) {
                    $userSelection->setSelected($this->userSelections[$key]->getSelected());
                }
            }
            $userSelections[$key] = $userSelection;
        }

        return $userSelections;
    }


    /**
     * Generate a list of available concrete options, selected as per role defaults, indexed by selection.key
     *
     * Each option representing a concrete item (as opposed to an abstract concept in the admin system)
     *
     * @param GenericCollection|Page\Option\Model[] $abstractOptions Abstract options
     * @return GenericCollection|Selection\Model[] Concrete selections
     */
    protected function buildRoleSelections(GenericCollection $abstractOptions)
    {
        // IMPORTANT:
        // This base implementation represents the case when there is a 1:1 between option.key and selection.key
        // This is the case for, eg, WorkType, AssessmentType, MarkPerCap etc.
        // It is NOT the case when a single option.key represents more than 1 selection.key, eg terms 'ALL' can match 2015, 2016 etc
        // In the latter case, you MUST override this method (see TermFilter for an example)

        $availableSelections = $this->filterSelectionMapper->createCollection();
        $roleType            = $this->user->getRole()->getType();
        $isUserConfigurable  = $this->pageFilter->getUserConfigurable();

        foreach ($abstractOptions as $option) {
            if ($isUserConfigurable && !$option->getAvailable()) {
                continue;
            }
            $key                       = $option->getKey();
            $selected                  = $option->getDefaults()[$roleType]->getSelected();
            $availableSelections[$key] = $this->createSelection($key, $selected);
        }
        return $availableSelections;
    }


    /**
     * Helper method to create a selection for this component
     *
     * @param string $key
     * @param bool   $selected
     * @return Selection\Model
     */
    protected function createSelection($key, $selected)
    {
        $selection = $this->filterSelectionMapper->createModel();
        $selection
            ->setFilter($this->definition->getFilter())
            ->setUser($this->user)
            ->setPage($this->pageFilter->getPage())
            ->setKey($key)
            ->setSelected($selected);
        return $selection;
    }


    /* Getters
     */


    /**
     * @return IDefinition
     */
    public function getDefinition(): IDefinition
    {
        return $this->definition;
    }


    /**
     * @return User
     */
    protected function getUser()
    {
        return $this->user;
    }


    /**
     * @return Page\Model
     */
    protected function getPageFilter()
    {
        return $this->pageFilter;
    }


    /**
     * @return null|GenericCollection
     */
    protected function getUrlOverrides(): ?GenericCollection
    {
        return $this->urlOverrides;
    }


    /**
     * @return Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }
}
