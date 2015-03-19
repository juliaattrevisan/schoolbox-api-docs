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

class External_Offline_Markbook implements External_MarkbookInterface
{
    /**
     * Returns the assessment information if found in remote system
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function getAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        // NOOP
        return array();
    }

    /**
     * Creates a new assessment item in the markbook
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function createAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        // NOOP
        return array();
    }

    /**
     * Updates an existing markbook assessment
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function updateAssessment($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox $assessment)
    {
        // NOOP
        return array();
    }

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork
     * @return bool
     */
    public function setMark($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork)
    {
        // NOOP
        return false;
    }

} 