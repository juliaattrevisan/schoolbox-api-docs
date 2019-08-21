<?php
/**
 * Model for submission box grade levels.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

class Model
{
    /**
     * Primary key
     *
     * @var int
     */
    protected $id;

    /**
     * Group this belongs to
     *
     * @var Group\Model
     */
    protected $group;

    /**
     * Lower bound for grades.
     * If a submission return's normalized mark is >= this value, and < any
     * other mark in the same group, the return has this label.
     *
     * @var int
     */
    protected $lowerBound;

    /**
     * Raw grade (0-100) for this selected grade.
     * If a submission return is given this grade, it is treated as having this
     * grade for averaging purposes.
     *
     * This, along with lower bounds
     *
     * @var int
     */
    protected $rawGrade;

    /**
     * Caption for this grade level.
     * This is used as the display value for submission returns.
     *
     * @var string
     */
    protected $caption;


    /**
     * @return string
     */
    public function getCaption()
    {
        return $this->caption;
    }

    /**
     * @param  string $caption
     * @return $this
     */
    public function setCaption($caption)
    {
        $this->caption = $caption;
        return $this;
    }

    /**
     * @return Group\Model
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @param  Group\Model $group
     * @return $this
     */
    public function setGroup($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param  int   $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getLowerBound()
    {
        return $this->lowerBound;
    }

    /**
     * @param  int   $lowerBound
     * @return $this
     */
    public function setLowerBound($lowerBound)
    {
        $this->lowerBound = $lowerBound;
        return $this;
    }

    /**
     * @return int
     */
    public function getRawGrade()
    {
        return $this->rawGrade;
    }

    /**
     * @param  int   $rawGrade
     * @return $this
     */
    public function setRawGrade($rawGrade)
    {
        $this->rawGrade = $rawGrade;
        return $this;
    }
}
