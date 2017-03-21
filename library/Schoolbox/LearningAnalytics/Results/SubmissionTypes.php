<?php
/**
 * SubmissionTypes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Assessment\Component\SubmissionBox\WorkType as SubmissionType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper as SubmissionTypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\LearningAnalytics\Metadata;

class SubmissionTypes {

    /**
     * A Collection of available submission types
     *
     * @var GenericCollection|SubmissionType[]
     */
    private $submissionTypes;

    /**
     * The SubmissionType instance corresponding to the selected type
     *
     * @var null|SubmissionType
     */
    private $selectedSubmissionTypeFilter;


    /**
     * Constructor
     *
     * @param Metadata $metadata
     */
    public function __construct(Metadata $metadata)
    {
        $this->submissionTypes = SubmissionTypeMapper::getInstance()->findAll();

        // Was a submission type specified in request ?
        $submissionTypeId = $metadata->getSelectedSubmissionTypeFilter();
        if($submissionTypeId){
            foreach ($this->submissionTypes as $submissionType) {
                if ($submissionType->getId() === $submissionTypeId) {
                    $this->selectedSubmissionTypeFilter = $submissionType;
                    break;
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Get all task types
     *
     * @return GenericCollection|SubmissionType[]
     */
    public function getSubmissionTypes()
    {
        return $this->submissionTypes;
    }

    /**
     * Get selected task filter
     *
     * @return SubmissionType
     */
    public function getSelectedActivityTypeFilter()
    {
        return $this->selectedSubmissionTypeFilter;
    }
}
