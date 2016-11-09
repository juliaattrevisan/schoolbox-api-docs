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
use \Schoolbox\Resource\Storage\Model as StorageFile;

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
     * An optional attached File
     *
     * @var StorageFile|null
     */
    protected $attachedFile = null;

    /**
     * @inheritdoc
     */
    public function getFamilyName()
    {
        return 'task';
    }

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
     * @return $this;
     */
    protected function setIsSelfAssessed($isSelfAssessed)
    {
        $this->isSelfAssessed = $isSelfAssessed;
        return $this;
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
     * @return null|StorageFile
     */
    public function getAttachedFile()
    {
        return $this->attachedFile;
    }

    /**
     * @param null|StorageFile $attachedFile
     * @return $this
     */
    public function setAttachedFile($attachedFile)
    {
        $this->attachedFile = $attachedFile;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        return $this->getPublishAt();
    }

    /**
     * @param \Alaress_Date_Format $openAt
     * @return static
     */
    public function setOpenAt($openAt)
    {
        $this->setPublishAt($openAt);
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        return $this->getDueAt();
    }

    /**
     * @param \Alaress_Date_Format $closeAt
     * @return static
     */
    public function setCloseAt($closeAt)
    {
        $this->setDueAt($closeAt);
        return $this;
    }
}
