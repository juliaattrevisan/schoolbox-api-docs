<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Filter\Selection;
use Schoolbox\Mapper;
use Schoolbox\Filter\Option;

/**
 * Common logic for Filter Components
 */
abstract class AbstractComponent implements IComponent
{

    /**
     * @var IDefinition
     */
    private $definition;

    /** @var User */
    private $user;

    /** @var string */
    private $page;

    /**
     * NULL if controller has specified to override the user's saved selections
     *
     * @var GenericCollection|Selection\Model[]|null
     */
    private $savedSelections;

    /**
     * If non-null, ignore user's saved selections and just select the items that match these override keys
     *
     * This gives the controller the ability to jump to specific data sets
     *
     * @var null|GenericCollection|Selection\Model[]
     */
    private $selectionOverrides;


    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * @param IDefinition                              $definition
     * @param User                                     $user
     * @param string                                   $page
     * @param null|GenericCollection|Selection\Model[] $savedSelections Existing selections for this filter, user and page
     * @param null|GenericCollection|Selection\Model[] $overrides
     * @param Selection\Mapper                         $filterSelectionMapper
     * @throws \DomainException If both saved selections and override selections are null
     */
    public function __construct(
        IDefinition $definition,
        User $user,
        $page,
        GenericCollection $savedSelections = null,
        GenericCollection $overrides = null,
        Selection\Mapper $filterSelectionMapper
    ) {
        // Ensure we have either saved user selections (even if empty) or override selections
        if(null === $savedSelections && null === $overrides){
            throw new \DomainException('saved selections and override selections cannot both be null');
        }

        $this->definition            = $definition;
        $this->user                  = $user;
        $this->page                  = $page;
        $this->savedSelections       = $savedSelections;
        $this->selectionOverrides    = $overrides;
        $this->filterSelectionMapper = $filterSelectionMapper;
    }


    /**
     * @inheritdoc
     */
    public function getTemplate()
    {
        return $this->definition->getPanelComponentTemplate();
    }


    /**
     * Generate a list of concrete options, selected as per user's selections
     *
     * @param GenericCollection|Option\Model[] $availableOptions
     * @return \Schoolbox\Filter\Selection\Model[]|GenericCollection
     */
    protected function getUserSelections(GenericCollection $availableOptions)
    {
        $availableSelections = $this->getRoleSelections($availableOptions);

        if ($this->selectionOverrides instanceof GenericCollection) {
            $availableSelections = $this->overlayOverrideSelections($availableSelections);
        } else {
            $availableSelections = $this->overlayUserSelections($availableSelections);
        }

        return $availableSelections;
    }


    /**
     * Generate a list of available concrete options, selected as per role defaults.
     *
     * Each option representing a concrete item (as opposed to an abstract concept in the admin system)
     *
     * @param GenericCollection|Option\Model[] $optionDefaults Abstract options
     * @return GenericCollection|Selection\Model[] Concrete selections
     */
    protected function getRoleSelections(GenericCollection $optionDefaults)
    {
        // IMPORTANT:
        // This base implementation represents the case when there is a 1:1 between option.key and selection.key
        // This is the case for, eg, WorkType, AssessmentType, etc.
        // It is NOT the case when a single option.key represents more than 1 selection.key, eg terms 'ALL' can match 2015, 2016 etc
        // In the latter case, you MUST override this method (see TermFilter for an example)

        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $roleType            = $this->getUser()->getRole()->getType();

        foreach ($optionDefaults as $option) {
            $key                       = $option->getKey();
            $selected                  = $option->getDefaults()[$roleType]->getSelected();
            $availableSelections[$key] = $this->createSelection($key, $selected);
        }
        return $availableSelections;
    }


    /**
     * Final step in deciding which options should be selected in a filter panel.
     *
     * Overlays the specific user's previously saved settings
     *
     * @param GenericCollection|Selection\Model[] $availableSelections
     * @return GenericCollection|Selection\Model[]
     */
    protected function overlayUserSelections(GenericCollection $availableSelections)
    {
        // Overlay user-specific selection settings
        $savedSelections = $this->savedSelections;
        foreach ($availableSelections as $key => $availableSelection) {
            if ($savedSelections->offsetExists($key)) {
                $availableSelection->setSelected($savedSelections[$key]->getSelected());
            }
        }

        return $availableSelections;
    }


    /**
     * Overlay the selections specified by the controller.
     *
     * @param GenericCollection|Selection\Model[] $availableSelections
     * @return GenericCollection|Selection\Model[]
     */
    protected function overlayOverrideSelections(GenericCollection $availableSelections)
    {
        // Overlay controller-injected selection settings
        foreach ($availableSelections as $key => $availableSelection) {
            $availableSelection->setSelected($this->selectionOverrides->keyExists($key) && $this->selectionOverrides[$key]->getSelected());
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
            ->setPage($this->page)
            ->setKey($key)
            ->setSelected($selected);
        return $selection;
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function toContext()
    {
        // Get option defaults for current user's roleType
        $roleType = $this->getUser()->getRole()->getType();

        /** @var GenericCollection|Option\Model[] $availableOptions */
        $availableOptions = $this->getDefinition()->getAvailableOptions($this->page, $roleType)[$this->page];

        $roleSelections = $this->getRoleSelections($availableOptions);
        $userSelections = $this->getUserSelections($availableOptions);

        return [
            'isAvailable'    => (bool)$userSelections->count(),
            'definition'     => $this->getDefinition(),
            'roleSelections' => $roleSelections,
            'userSelections' => $userSelections,
        ];
    }


    /* Getters
     */


    /**
     * @return IDefinition
     */
    public function getDefinition()
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
     * @return string
     */
    protected function getPage()
    {
        return $this->page;
    }


    /**
     * @return Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }
}
