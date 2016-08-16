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

namespace Schoolbox\Learning\Assessment;


class Task extends AbstractModel
{

    /**
     * This Task has been completed either:
     *  - by student, as self-assessed
     *  - by teacher, as assessed
     *
     * @var bool
     */
    protected $isCompleted = false;

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
}