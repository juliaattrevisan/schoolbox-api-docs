<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Quiz\Attempt;

use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use Schoolbox\User;
use \Alaress_Date_Format;

/**
 * Represents a Student's Attempt at a Quiz
 */
class Model
{
    /**
     * @var int
     */
    private $id;

    /**
     * The Quiz attempted
     *
     * @var Quiz
     */
    private $quiz;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Alaress_Date_Format
     */
    private $start;

    /**
     * @var Alaress_Date_Format
     */
    private $finish;

    /**
     * @var Alaress_Date_Format
     */
    private $reviewedAt;

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
     * @return Quiz
     */
    public function getQuiz()
    {
        return $this->quiz;
    }

    /**
     * @param Quiz $quiz
     * @return static
     */
    public function setQuiz(Quiz $quiz)
    {
        $this->quiz = $quiz;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return static
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param Alaress_Date_Format $start
     * @return static
     */
    public function setStart(Alaress_Date_Format $start)
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getFinish()
    {
        return $this->finish;
    }

    /**
     * @param Alaress_Date_Format $finish
     * @return static
     */
    public function setFinish(Alaress_Date_Format $finish)
    {
        $this->finish = $finish;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getReviewedAt()
    {
        return $this->reviewedAt;
    }

    /**
     * @param Alaress_Date_Format $reviewedAt
     * @return static
     */
    public function setReviewedAt(Alaress_Date_Format $reviewedAt)
    {
        $this->reviewedAt = $reviewedAt;
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