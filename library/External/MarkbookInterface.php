<?php
/**
 * This interface defines how to store markbook data in a remote SIS
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

use \Schoolbox\Assessment\Component\SubmissionBox;
use \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;

interface External_MarkbookInterface
{

    /**
     * Returns the assessment information if found in remote system
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function getAssessment(SubmissionBox $assessment);

    /**
     * Creates a new assessment item in the markbook
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function createAssessment(SubmissionBox $assessment);

    /**
     * Updates an existing markbook assessment
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox $assessment
     * @return array
     */
    public function updateAssessment(SubmissionBox $assessment);

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork $returnedWork
     * @return bool
     */
    public function setMark(ReturnedWork $returnedWork);

}


