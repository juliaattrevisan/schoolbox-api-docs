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

use Schoolbox\Collection;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\LearningAnalytics\Metadata\Base as Metadata;

class TaskTypes {

    /**
     * A Collection of task types
     *
     * @var Collection
     */
    private $allTaskTypes = null;

    /**
     * Request metadata
     *
     * @var Metadata
     */
    private $metadata = null;

    /**
     * The WorkType instance corresponding to the selected type
     *
     * @var WorkType
     */
    private $selectedTaskFilter = null;

    /**
     * Constructor
     */
    public function __construct(Metadata $metadata) {
        $this->setAllTaskTypes(new Collection());
        $this->setSelectedTaskFilter(new WorkType());
        $this->setMetadata($metadata);

        $this->process();
    }

    /**
     * Process
     */
    private function process() {
        $workTypeMapper = new WorkTypeMapper();
        $this->setAllTaskTypes($workTypeMapper->findAll());

        /* Save yourself a db call with a quick iteration instead
         */
        foreach($this->getAllTaskTypes() as $currentTaskType) {
            if ($currentTaskType->getId() == $this->getMetadata()->getSelectedTaskIdFilter()) {
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
     * @param \Schoolbox\Collection $allTaskTypes
     */
    private function setAllTaskTypes($allTaskTypes)
    {
        $this->allTaskTypes = $allTaskTypes;
    }

    /**
     * Get all task types
     *
     * @return \Schoolbox\Collection
     */
    public function getAllTaskTypes()
    {
        return $this->allTaskTypes;
    }

    /**
     * Set metadata
     *
     * @param \Schoolbox\LearningAnalytics\Metadata\Base $metadata
     */
    private function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * Get metadata
     *
     * @return \Schoolbox\LearningAnalytics\Metadata\Base
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
    private function setSelectedTaskFilter($selectedTaskFilter)
    {
        $this->selectedTaskFilter = $selectedTaskFilter;
    }

    /**
     * Get selected task filter
     *
     * @return WorkType
     */
    public function getSelectedTaskFilter()
    {
        return $this->selectedTaskFilter;
    }
}