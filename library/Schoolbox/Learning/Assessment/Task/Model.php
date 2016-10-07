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

use Schoolbox\Collection;
use Schoolbox\Learning\MarkScheme\Completion;
use \Schoolbox\User;
use \Schoolbox\Learning\Assessment\AbstractAssessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;

class Model extends AbstractAssessment
{
    /**
     * Is this task self-markable?
     *
     * @var bool
     */
    protected $isSelfAssessed = false;

    /**
     * The content this task links to (if any).
     *
     * @var string
     */
    protected $url = '';

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
        if (!is_null($gradeGroup)) {
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

    /**
     * This task is student self-assessed
     *
     * @return boolean
     */
    public function isSelfAssessed()
    {
        return $this->isSelfAssessed == true;
    }

    /**
     * This task is teacher-assessed
     *
     * @return bool
     */
    public function isTeacherAssessed()
    {
        return $this->isSelfAssessed == false;
    }

    /**
     * Set this task to be teacher-assessed
     *
     * @return $this
     */
    public function teacherAssessed()
    {
        $this->setIsSelfAssessed(false);
        return $this;
    }

    /**
     * Set this task to be self-assessed
     *
     * @return $this
     */
    public function selfAssessed()
    {
        $this->setIsSelfAssessed(true);
        return $this;
    }

    /* Getters/Setters
     */

    /**
     * @param boolean $isSelfAssessed
     */
    protected function setIsSelfAssessed($isSelfAssessed)
    {
        $this->isSelfAssessed = $isSelfAssessed;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this;
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return bool
     */
    public function isSelfMarkable()
    {
        return $this->getSelfMarkable();
    }

    /**
     * @return bool
     */
    public function getSelfMarkable()
    {
        return $this->selfMarkable;
    }

    /**
     * @param bool $selfMarkable
     * @return static
     */
    public function setSelfMarkable($selfMarkable)
    {
        $this->selfMarkable = $selfMarkable;
        return $this;
    }
}
