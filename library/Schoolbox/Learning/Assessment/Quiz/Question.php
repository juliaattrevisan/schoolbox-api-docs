<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\Quiz\Feedback\Model as QuizAnswer;

class Question
{
    // TODO - This is all just placeholder for testing. Will be replaced by SA-701

    /** @var array */
    private $question;

    /** @var Solution */
    private $solution;

    /** @var  Collection|QuizAnswer[] */
    private $answers;


    public function __construct(array $question)
    {
        $this->question = $question;
        $this->answers = new Collection();
    }

    /**
     * @return float
     */
    public function getMarks()
    {
        return $this->question['marks'];
    }

    /**
     * @return Solution
     */
    public function getSolution()
    {
        return $this->solution;
    }

    /**
     * @param Solution $solution
     * @return static
     */
    public function setSolution(Solution $solution)
    {
        $this->solution = $solution;
        return $this;
    }

    /**
     * @return Collection|QuizAnswer[]
     */
    public function getAnswers()
    {
        return $this->answers;
    }

    /**
     * @param Collection|QuizAnswer[] $answers
     * @return static
     */
    public function setAnswers(Collection $answers)
    {
        $this->answers = $answers;
        return $this;
    }

}
