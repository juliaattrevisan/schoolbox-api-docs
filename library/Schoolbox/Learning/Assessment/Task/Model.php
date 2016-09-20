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
     * Get the Human Readable Mark value for a particular User
     *
     * Optionally get the Published Human Readable Mark or the
     * currently-unpublished Human Readable Mark
     *
     * @param User $user
     * @param bool $publishedOnly
     * @return string
     */
    public function getHumanReadableMark(User $user, $publishedOnly)
    {
        $participant = $this->getParticipant($user);

        /* Get the Mark that was Published
         */
        if ($publishedOnly) {
            $feedback = $participant->findMostRecentPublishedFeedback($this);

            if ($feedback) {
                return 'Published Feedback Exists';
            }
        }
        /* Otherwise just use the latest Feedback's current Mark value
         */
        else {
            $feedback = $participant->findMostRecentFeedback($this);

            if ($feedback) {
                return 'Feedback Exists';
            }
        }

        /* If there was no Feedback, then check for Completions
         */
        $responseCount = $participant->findAllResponsesForAssessable($this)->count();

        if ($responseCount) {
            return 'Response Exists';
        }

        return 'Incomplete';
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