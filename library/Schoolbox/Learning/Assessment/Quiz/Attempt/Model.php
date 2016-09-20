<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Attempt;

use \Schoolbox\Learning\Assessment\AbstractResponse;

class Model extends AbstractResponse
{
    /**
     * All the Responses to this Quiz
     *
     * These are Students' Responses to individual Questions
     * that were asked in the Quiz's Sections
     *
     * @var array
     */
    protected $allResponses = [];

    /**
     * Questions asked in this feedback's QuizAttempt
     *
     * @var array
     */
    protected $allQuestions = [];

    /**
     * Attempts are submitted at the updatedAt value
     * in case the Attempt is modified by the Student
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->getUpdatedAt();
    }

    /**
     * @return float
     */
    public function getMarksAvailable()
    {
        $marksAvailable = 0.0;
        foreach ($this->allQuestions as $question) {
            $marksAvailable += $question['marks'];
        }
        return $marksAvailable;
    }


    /**
     * @return array
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param array $allResponses
     * @return self
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
        return $this;
    }
}