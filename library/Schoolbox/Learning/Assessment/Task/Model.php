<?php
/**
 * Task
 *
 * A generic "thing to do" that doesn't have a Mark value. A Task
 * typically represents things like "read this book", "prepare this
 * thing before the class" etc.
 *
 * Tasks can be self-assessed (like a to-do list for students)
 * or they can be asssessed by a teacher ("student read the book", etc)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Task;

use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;

class Model extends AbstractAssessment
{
    /**
     * @inheritdoc
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Task';
    }

    /**
     * Tasks always return null.
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return null
     */
    public function getNormalisedMark(User $user, $publishedOnly)
    {
        return null;
    }

    /**
     * @param GradeGroup|null $gradeGroup
     * @return static
     */
    public function setGradeGroup(GradeGroup $gradeGroup = null)
    {
        if(null !== $gradeGroup){
            throw new \InvalidArgumentException('Tasks cannot have mark type of Letter Grade.');
        }
        return $this;
    }

    /**
     * @return null
     */
    public function getMaxMark()
    {
        return null;
    }

    /**
     * Tasks do not have any weight.
     *
     * @return int
     */
    public function getWeight()
    {
        return 0;
    }
}