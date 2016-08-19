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

class AbstractFeedback implements IFeedback
{
    /**
     * Mark given to this Assessable
     *
     * @var float
     */
    protected $mark;

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