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
     * @inheritdoc
     *
     * Use the most recent Published Feedback as the official value
     *
     * @param User $user
     * @return string
     */
    public function calculateMark(User $user)
    {
        return $this->findParticipant($user)->findMostRecentPublishedFeedback($this)->calculateMark();
    }
}