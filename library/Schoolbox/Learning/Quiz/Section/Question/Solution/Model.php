<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question\Solution;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Represents the correct Solution to a Quiz Questions
 * 'Solutions' were formerly known as 'Answers'.
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var QuizQuestion
     */
    private $question;

    /**
     * 1-based
     *
     * @var int
     */
    private $sequence;

    /**
     * @var string
     */
    private $solution;

    /**
     * Clears ID so subsequent saves are INSERT instead of UPDATE
     * @return void
     */
    function __clone()
    {
        // clear ID + reference
        $this->id = null;
        $this->question = null;
    }

    /* Getters / Setters
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
     */
    public function setQuestion(QuizQuestion $question)
    {
        $this->question = $question;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }

    /**
     * @return string
     */
    public function getSolution()
    {
        return $this->solution;
    }

    /**
     * @param string $solution
     * @return static
     */
    public function setSolution($solution)
    {
        $this->solution = $solution;
        return $this;
    }
}
