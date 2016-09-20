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

use Schoolbox\Collection;
use \Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Learning\Assessment\Quiz\Response as QuizResponse;
use Schoolbox\Learning\Assessment\Quiz\Question as QuizQuestion;

class Model extends AbstractResponse
{
    /**
     * All the Responses to this Quiz
     *
     * These are Students' Responses to individual Questions
     * that were asked in the Quiz's Sections
     *
     * @var Collection|QuizResponse[]
     */
    protected $allResponses;

    /**
     * Questions asked in this feedback's QuizAttempt
     *
     * @var Collection|QuizQuestion[]
     */
    protected $allQuestions;

    public function __construct()
    {
        parent::__construct();
        $this->allResponses = new Collection();
        $this->allQuestions = new Collection();
    }


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
            $marksAvailable += $question->getMarks();
        }
        return $marksAvailable;
    }


    /**
     * @return Collection|QuizResponse[]
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param Collection|QuizResponse[] $allResponses
     * @return static
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
        return $this;
    }

    /**
     * @return Collection|QuizQuestion[]
     */
    public function getAllQuestions()
    {
        return $this->allQuestions;
    }

    /**
     * @param Collection|QuizQuestion[] $allQuestions
     * @return static
     */
    public function setAllQuestions($allQuestions)
    {
        $this->allQuestions = $allQuestions;
        return $this;
    }
}