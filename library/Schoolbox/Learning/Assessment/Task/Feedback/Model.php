<?php

/**
 * Model
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Task\Feedback;

use \Schoolbox\Learning\Assessment\AbstractFeedback;

class Model extends AbstractFeedback
{
    /**
     * This Task has been completed by Student
     *
     * @var bool
     */
    protected $completedByStudent = false;

    /**
     * This Task has been completed by Teacher
     *
     * @var bool
     */
    protected $completedByTeacher = false;

    /**
     * Calculate the final mark for this IAssessable
     *
     * Tasks aren't marked and always return null
     *
     * @return null
     */
    public function calculateMark()
    {
        return null;
    }

    /* Getters/Setters
     */

    /**
     * @return boolean
     */
    public function isCompletedByStudent()
    {
        return $this->completedByStudent;
    }

    /**
     * @param boolean $completedByStudent
     * @return self
     */
    public function setCompletedByStudent($completedByStudent)
    {
        $this->completedByStudent = $completedByStudent;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isCompletedByTeacher()
    {
        return $this->completedByTeacher;
    }

    /**
     * @param boolean $completedByTeacher
     * @return self
     */
    public function setCompletedByTeacher($completedByTeacher)
    {
        $this->completedByTeacher = $completedByTeacher;
        return $this;
    }
}