<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Quiz\Response as QuizResponse;

/**
 * A Quiz Question authored by a Teacher
 */
class Question
{
    /** @var array */
    private $question;

    /** @var Answer */
    private $answer;

    /** @var  Collection|QuizResponse[] */
    private $responses;


    public function __construct(array $question)
    {
        $this->question = $question;
        $this->responses = new Collection();
    }

    /**
     * @return float
     */
    public function getMarks()
    {
        return $this->question['marks'];
    }

    /**
     * @return Answer
     */
    public function getAnswer()
    {
        return $this->answer;
    }

    /**
     * @param Answer $answer
     * @return static
     */
    public function setAnswer(Answer $answer)
    {
        $this->answer = $answer;
        return $this;
    }

    /**
     * @return Collection|QuizResponse[]
     */
    public function getResponses()
    {
        return $this->responses;
    }

    /**
     * @param Collection|QuizResponse[] $responses
     * @return static
     */
    public function setResponses(Collection $responses)
    {
        $this->responses = $responses;
        return $this;
    }

}
