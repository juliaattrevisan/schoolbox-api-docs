<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section;

use Schoolbox\ISoftDeletable;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Alaress_Date_Format;

/**
 * Represents a set of Questions in a Quiz
 */
class Model implements ISoftDeletable
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var Quiz
     */
    private $quiz;

    /**
     * @var int
     */
    private $sequence;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var bool
     */
    private $random;

    /**
     * @var int
     */
    private $pool;

    /**
     * @var Alaress_Date_Format
     */
    private $deletedAt;

    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return !$this->getDeletedAt()->isNull();
    }

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
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return bool
     */
    public function getRandom()
    {
        return $this->random;
    }

    /**
     * @param bool $random
     * @return static
     */
    public function setRandom($random)
    {
        $this->random = $random;
        return $this;
    }

    /**
     * @return int
     */
    public function getPool()
    {
        return $this->pool;
    }

    /**
     * @param int $pool
     * @return static
     */
    public function setPool($pool)
    {
        $this->pool = $pool;
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