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
     * @return array
     */
    public function getAssessment(Activity $assessment): array;

    /**
     * Indicates if assessment exists in external system's markbook
     *
     * @param Activity $assessment
     * @return bool
     */
    public function hasAssessment(Activity $assessment): bool;

    /**
     * Creates a new assessment item in the markbook
     *
     * @param Activity $assessment
     * @return bool
     */
    public function createAssessment(Activity $assessment): bool;

    /**
     * Updates an existing markbook assessment
     *
     * @param Activity $assessment
     * @return bool
     */
    public function updateAssessment(Activity $assessment): bool;

    /**
     * Deletes an existing markbook assessment
     *
     * @param Activity $assessment
     * @return bool
     */
    public function deleteAssessment(Activity $assessment): bool;

    /**
     * Creates or updates the mark for a student in the remote markbook
     *
     * @param ReturnedWork|QuizAttempt $returnedWork
     * @return bool
     */
    public function setMark($returnedWork): bool;
}

