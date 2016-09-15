<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Feedback;

use \Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\Quiz\Question as QuizQuestion;

/**
 * This is a Student's Response to an individual Question
 * that was asked in the Quiz's Sections
 */
class Model extends AbstractFeedback
{
    /**
     * @var array
     */
    private $response = [];

    /**
     * @var QuizQuestion
     */
    private $question;

    /**
     * Calculate the final mark for this IAssessable
     */
    public function getNormalisedMark()
    {
        $sum = 0;
        $total = 0;

        foreach ($this->getAllResponses() as $currentResponse) {
            $sum += $currentResponse['mark'];
            $total += $currentResponse['available'];
        }

        /* No marks because no questions with mark values
         */
        if ($total == 0) {
            return null;
        }

        return 100 * (float)$sum / (float)$total;
    }

    /* Getters/Setters
     */

    /**
     * @return QuizQuestion
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * @param QuizQuestion $question
     * @return static
     * @todo add Type Declaration when implemented
     */
    public function setQuestion($question)
    {
        $this->question = $question;
        return $this;
    }
}