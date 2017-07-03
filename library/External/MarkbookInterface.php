<?php
/**
 * This interface defines how to store markbook data in a remote SIS
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Assessment\Activity;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;

interface External_MarkbookInterface
{

    /**
     * Returns the assessment information if found in remote system
     *
     * @param Activity $assessment
     * @param int|null $year
     * @param int|null $semester
     * @return array
     */
    public function getAssessment(Activity $assessment, $year = null, $semester = null);

    /**
     * Creates or update an assessment item in the markbook
     *
     * @param Activity $assessment
     * @param int|null $year
     * @param int|null $semester
     * @return bool
     */
    public function createOrUpdateAssessment(Activity $assessment, $year = null, $semester = null);

    /**
     * Creates a new assessment item in the markbook
     *
     * @param Activity $assessment
     * @param int|null $year
     * @param int|null $semester
     * @return bool
     */
    public function createAssessment(Activity $assessment, $year = null, $semester = null);

    /**
     * Updates an existing markbook assessment
     *
     * @param Activity $assessment
     * @param int|null $year
     * @param int|null $semester
     * @return bool
     */
    public function updateAssessment(Activity $assessment, $year = null, $semester = null);

    /**
     * Deletes an existing markbook assessment
     *
     * @param Activity $assessment
     * @param int|null $year
     * @param int|null $semester
     * @return bool
     */
    public function deleteAssessment(Activity $assessment, $year = null, $semester = null);

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param ReturnedWork|QuizAttempt $returnedWork
     * @param int|null $year
     * @param int|null $semester
     * @return bool
     */
    public function setMark($returnedWork, $year = null, $semester = null);

}

