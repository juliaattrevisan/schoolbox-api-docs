<?php
/**
 * Model for submission box grade levels.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * Caption for this grade level.
     * This is used as the display value for submission returns.
     *
     * @var string
     */
    protected $caption;

    /**
     * Constructor - puts an empty group object in.
     */
    public function __construct()
    {
        $this->setGroup(new Group\Model());
    }

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
}
