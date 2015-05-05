<?php
/**
 * Provides the offline version of the MarkbookInterface
 * This is used by all connectors if it is not implemented.  The purpose is just to do nothing, because we have no
 * markbook to integrate with.
 * At the time of writing this is only implemented for Synergetic so this version will be used for all other systems
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

use \Schoolbox\Assessment\Activity;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use \Schoolbox\Assessment\Component\OnlineAssessment\Attempt;

class External_Offline_Markbook implements External_MarkbookInterface
{
    /**
     * Returns the assessment information if found in remote system
     *
     * @param Activity $assessment
     * @return array
     */
    public function getAssessment(Activity $assessment)
    {
        // NOOP
        return array();
    }

    /**
     * Creates a new assessment item in the markbook
     *
     * @param Activity $assessment
     * @return array
     */
    public function createAssessment(Activity $assessment)
    {
        // NOOP
        return array();
    }

    /**
     * Updates an existing markbook assessment
     *
     * @param Activity $assessment
     * @return array
     */
    public function updateAssessment(Activity $assessment)
    {
        // NOOP
        return array();
    }

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param ReturnedWork|Attempt $returnedWork
     * @return bool
     */
    public function setMark($returnedWork)
    {
        // NOOP
        return false;
    }

} 