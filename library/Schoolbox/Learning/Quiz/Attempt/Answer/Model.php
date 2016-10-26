<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Quiz\Attempt\Answer;

use Schoolbox\Learning\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestion;
use Alaress_Date_Format;

/**
 * Represents a Students Answer to a Quiz Questions
 * 'Answers' were formerly known as 'Responses'.
 * Name changed to avoid naming collision with Quiz IResponse
 */
class Model
{
    /**
     * @var int
     */
    private $id;

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
    private $automarkedAlready;

    /**
     * @var Alaress_Date_Format
     */
    private $updatedAt;

    /**
     * @var Alaress_Date_Format
     */
    private $deletedAt;


    /* Getters / Setters
     */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

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
    public function isAutomarkedAlready()
    {
        return $this->automarkedAlready;
    }

    /**
     * @param boolean $automarkedAlready
     * @return static
     */
    public function setAutomarkedAlready($automarkedAlready)
    {
        $this->automarkedAlready = $automarkedAlready;
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

    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param Alaress_Date_Format $deletedAt
     * @return static
     */
    public function setDeletedAt(Alaress_Date_Format $deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}