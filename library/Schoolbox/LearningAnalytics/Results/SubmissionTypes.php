<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics\Results;

use Schoolbox\Assessment\Component\SubmissionBox\WorkType as SubmissionType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper as SubmissionTypeMapper;
use Schoolbox\GenericCollection;

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
    private $selectedSubmissionType;


    /**
     * Constructor
     *
     * @param null|int $submissionTypeId
     */
    public function __construct($submissionTypeId = null)
    {
        $this->submissionTypes = SubmissionTypeMapper::getInstance()->findAll();

        // Was a submission type specified in request ?
        if(null !== $submissionTypeId){
            foreach ($this->submissionTypes as $submissionType) {
                if ($submissionType->getId() === $submissionTypeId) {
                    $this->selectedSubmissionType = $submissionType;
                    break;
                }
            }
        }
    }

    /* Getters/Setters
     */

    /**
     * Get all available submission types
     *
     * @return GenericCollection|SubmissionType[]
     */
    public function getSubmissionTypes()
    {
        return $this->submissionTypes;
    }

    /**
     * Get selected submissionType filter
     *
     * @return null|SubmissionType
     */
    public function getSelectedSubmissionType()
    {
        return $this->selectedSubmissionType;
    }
}
