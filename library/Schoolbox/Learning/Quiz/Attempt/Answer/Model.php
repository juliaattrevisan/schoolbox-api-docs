<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Attempt\Answer;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Alaress_Date_Format;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Represents a Students Answer to a Quiz Questions
 * 'Answers' were formerly known as 'Responses'.
 * Name changed to avoid naming collision with Quiz IResponse
 */
class Model implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * @var QuizAttempt
     */
    private $attempt;

    // Intentionally no superfluous $section

    /**
     * @var QuizQuestion
     */
    private $question;

    /**
     * @var string
     */
    private $answer;

    /**
     * @var string
     */
    private $comment;

    /**
     * @var float
     */
    private $mark;

    /**
     * @var bool
     */
    private $autoMarked;

    /**
     * @var Alaress_Date_Format
     */
    private $updatedAt;

    /* Getters / Setters
     */

    /**
     * @return QuizAttempt
     */
    public function getAttempt()
    {
        return $this->attempt;
    }

    /**
     * @param QuizAttempt $attempt
     * @return static
     */
    public function setAttempt(QuizAttempt $attempt)
    {
        $this->attempt = $attempt;
        return $this;
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

    /**
     * @return string
     */
    public function getAnswer()
    {
        return $this->answer;
    }

    /**
     * @param string $answer
     * @return static
     */
    public function setAnswer($answer)
    {
        $this->answer = $answer;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     * @return static
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return float
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param float $mark
     * @return static
     */
    public function setMark($mark)
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoMarked()
    {
        return $this->autoMarked;
    }

    /**
     * @param boolean $autoMarked
     * @return static
     */
    public function setAutoMarked($autoMarked)
    {
        $this->autoMarked = $autoMarked;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param Alaress_Date_Format $updatedAt
     * @return static
     */
    public function setUpdatedAt(Alaress_Date_Format $updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
