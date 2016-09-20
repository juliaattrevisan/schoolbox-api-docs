<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Schoolbox\Learning\Assessment\Quiz\Question as QuizQuestion;

/**
 * DB entity wrapper
 */
class Response
{
    /** @var array */
    private $response = [];

    /** @var QuizQuestion */
    private $question;

    public function __construct(array $response)
    {
        $this->response = $response;
    }

    /**
     * @return float
     */
    public function getMark()
    {
        return $this->response['mark'];
    }

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
     */
    public function setQuestion(QuizQuestion $question)
    {
        $this->question = $question;
        return $this;
    }
}
