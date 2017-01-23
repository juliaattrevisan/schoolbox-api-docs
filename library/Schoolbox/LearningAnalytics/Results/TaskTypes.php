<?php
/**
 * TaskTypes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\LearningAnalytics\Metadata;

class TaskTypes {

    /**
     * A Collection of task types
     *
     * @var GenericCollection|WorkType[]
     */
    private $activityTypes;

    /**
     * Request metadata
     *
     * @var Metadata
     */
    private $metadata;

    /**
     * The WorkType instance corresponding to the selected type
     *
     * @var WorkType
     */
    private $selectedTaskFilter;


    /**
     * Constructor
     *
     * @param Metadata $metadata
     */
    public function __construct(Metadata $metadata)
    {
        $this->setSelectedTaskFilter(new WorkType());
        $this->setMetadata($metadata);

        $this->setActivityTypes(WorkTypeMapper::getInstance()->findAll());

        /* Save yourself a db call with a quick iteration instead
         */
        foreach ($this->getActivityTypes() as $currentTaskType) {
            if ($currentTaskType->getId() == $this->getMetadata()->getSelectedActivityTypeFilter()) {
                $this->setSelectedTaskFilter($currentTaskType);

                break;
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Set all task types
     *
     * @param GenericCollection|WorkType[] $allTaskTypes
     */
    private function setActivityTypes(GenericCollection $allTaskTypes)
    {
        $this->activityTypes = $allTaskTypes;
    }

    /**
     * Get all task types
     *
     * @return GenericCollection|WorkType[]
     */
    public function getActivityTypes()
    {
        return $this->activityTypes;
    }

    /**
     * Set metadata
     *
     * @param Metadata $metadata
     */
    private function setMetadata(Metadata $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    private function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set selected task filter
     *
     * @param WorkType $selectedTaskFilter
     */
    private function setSelectedTaskFilter(WorkType $selectedTaskFilter)
    {
        $this->selectedTaskFilter = $selectedTaskFilter;
    }

    /**
     * Get selected task filter
     *
     * @return WorkType
     */
    public function getSelectedActivityTypeFilter()
    {
        return $this->selectedTaskFilter;
    }
}
