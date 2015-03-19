<?php
/**
 * This interface defines how to store markbook data in a remote SIS
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

interface External_MarkbookInterface
{

    /**
     * Returns the assessment information if found in remote system
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function getAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment);

    /**
     * Creates a new assessment item in the markbook
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function createAssessment(\Schoolbox\Assessment\Component\SubmissionBox $assessment);

    /**
     * Updates an existing markbook assessment
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function updateAssessment($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox $assessment);

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param string
     * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork
     * @return bool
     */
    public function setMark($assessmentRemoteID, \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork);

}


