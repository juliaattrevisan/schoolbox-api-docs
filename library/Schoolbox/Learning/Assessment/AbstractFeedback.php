<?php
/**
 * AbstractFeedback
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\User;

class AbstractFeedback implements IFeedback
{
    /**
     * @var int
     */
    protected $id;

    /**
     * Mark given to this Assessable
     *
     * @var float
     */
    protected $mark;

    /**
     * The mark out of 100 given to this Assessable
     *
     * @var
     */
    protected $normalisedMark;

    /**
     * @var string
     */
    protected $comment;

    /**
     * The person who reviewed this Feedback
     *
     * @var User
     */
    protected $reviewer;

    /**
     * The datetime at which this Feedback was made visible to Students
     *
     * @var \Alaress_Date_Format
     */
    protected $publishedAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $createdAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $updatedAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $deletedAt;

    /**
     * Calculate the final mark for this IAssessable
     */
    public function calculateMark()
    {
        return $this->getMark();
    }

    /**
     * This IAssessable has a mark value of null
     *
     * @return bool
     */
    public function isNullMark()
    {
        return $this->calculateMark() === null;
    }

    /* Getters/Setters
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
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return float
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param $mark
     * @return $this
     */
    public function setMark($mark)
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getNormalisedMark()
    {
        return $this->normalisedMark;
    }

    /**
     * @param mixed $normalisedMark
     */
    public function setNormalisedMark($normalisedMark)
    {
        $this->normalisedMark = $normalisedMark;
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
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * @return User
     */
    public function getReviewer()
    {
        return $this->reviewer;
    }

    /**
     * @param User $reviewer
     */
    public function setReviewer($reviewer)
    {
        $this->reviewer = $reviewer;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    /**
     * @param \Alaress_Date_Format $publishedAt
     */
    public function setPublishedAt($publishedAt)
    {
        $this->publishedAt = $publishedAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \Alaress_Date_Format $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \Alaress_Date_Format $updatedAt
     * @return $this
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * @param \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}