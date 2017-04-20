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
abstract class AbstractFilterComponent implements IFilterComponent
{

    /**
     * @var IFilterDefinition
     */
    private $definition;

    /** @var User */
    private $user;

    /** @var string */
    private $page;

    /** @var GenericCollection|Selection\Model[] */
    private $savedSelections;

    /** @var Selection\Mapper */
    private $filterSelectionMapper;


    /**
     * @param IFilterDefinition $definition
     * @param User              $user
     * @param string            $page
     * @param GenericCollection $savedSelections Existing selections for this filter, user and page
     * @param Selection\Mapper  $filterSelectionMapper
     */
    public function __construct(IFilterDefinition $definition, User $user, $page, GenericCollection $savedSelections, Selection\Mapper $filterSelectionMapper)
    {
        $this->definition            = $definition;
        $this->user                  = $user;
        $this->page                  = $page;
        $this->savedSelections       = $savedSelections;
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
     * @param GenericCollection|Option\Model[] $availableOptions
     * @return \Schoolbox\Filter\Selection\Model[]|GenericCollection
     * @throws Mapper\Exception
     */
    protected function getUserSelections(GenericCollection $availableOptions)
    {
        $availableSelections = $this->getDefaultSelections($availableOptions);
        $this->overlayUserSelections($availableSelections);

        return $availableSelections;
    }


    /**
     * Generate a list of available options, selected as per role defaults.
     *
     * Each option representing a concrete item (as opposed to an abstract concept in the admin system)
     *
     * @param GenericCollection|Option\Model[] $optionDefaults $optionDefaults
     * @return GenericCollection|Selection\Model[]
     */
    protected function getDefaultSelections(GenericCollection $optionDefaults)
    {
        // IMPORTANT:
        // This base implementation represents the case when there is a 1:1 between option.key and selection.key
        // This is the case for, eg, WorkType, AssessmentType, etc.
        // It is NOT the case when a single option.key represents more than 1 selection.key, eg terms 'ALL' can match 2015, 2016 etc
        // In the latter case, you MUST override this method (see TermFilter for an example)

        $availableSelections = $this->getFilterSelectionMapper()->createCollection();
        $filter              = $this->getDefinition()->getFilter();
        $roleType            = $this->getUser()->getRole()->getType();

        foreach ($optionDefaults as $option) {

            $selection = $this->getFilterSelectionMapper()->createModel();
            $selection
                ->setFilter($filter)
                ->setUser($this->getUser())
                ->setPage($this->getPage())
                ->setKey($option->getKey())
                ->setSelected($option->getDefaults()[$roleType]->getSelected());
            $availableSelections[$selection->getKey()] = $selection;
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
        $savedSelections = $this->getSavedSelections();
        foreach ($availableSelections as $key => $availableSelection) {
            if ($savedSelections->offsetExists($key)) {
                $availableSelection->setSelected($savedSelections[$key]->getSelected());
            }
        }

        return $availableSelections;
    }


    /**
     * @inheritdoc
     * @throws Mapper\Exception
     */
    public function toContext()
    {
        // Get option defaults for current user's roleType
        $page     = $this->getPage();
        $roleType = $this->getUser()->getRole()->getType();

        /** @var GenericCollection|Option\Model[] $availableOptions */
        $availableOptions = $this->getDefinition()->getAvailableOptions($page, $roleType)[$page];

        return [
            'isAvailable'       => $availableOptions->count() ? true : false,
            'definition'        => $this->getDefinition(),
            'defaultSelections' => $this->getDefaultSelections($availableOptions),
            'userSelections'    => $this->getUserSelections($availableOptions),
        ];
    }


    /* Getters
     */


    /**
     * @return IFilterDefinition
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
     * @return Selection\Model[]|GenericCollection
     */
    protected function getSavedSelections()
    {
        return $this->savedSelections;
    }


    /**
     * @return Selection\Mapper
     */
    protected function getFilterSelectionMapper()
    {
        return $this->filterSelectionMapper;
    }
}
