<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;
use Alaress_Date_Format;

/**
 * Class Mark
 *
 * Semantically, this class should really be named Selection or similar, but for the sake of matching the db table, mark it is...
 *
 * @package Schoolbox\Assessment\Component\SubmissionBox\Rubric
 */
class Mark
{
    use Id;
    use CreatedAtDate;

    /** @var null|ReturnedWork */
    private $submissionReturn;

    /** @var User */
    private $student;

    /** @var Descriptor */
    private $descriptor;

    /** @var Alaress_Date_Format */
    private $publishedAt;

    /** @var bool */
    private $valid;


    /**
     * @return null|ReturnedWork
     */
    public function getSubmissionReturn(): ?ReturnedWork
    {
        return $this->submissionReturn;
    }

    /**
     * @param null|ReturnedWork $submissionReturn
     * @return $this
     */
    public function setSubmissionReturn(?ReturnedWork $submissionReturn)
    {
        $this->submissionReturn = $submissionReturn;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param User $student
     * @return static
     */
    public function setStudent(User $student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return Descriptor
     */
    public function getDescriptor()
    {
        return $this->descriptor;
    }

    /**
     * @param Descriptor $descriptor
     * @return static
     */
    public function setDescriptor(Descriptor $descriptor)
    {
        $this->descriptor = $descriptor;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishedAt()
    {
        return $this->publishedAt;
    }

    /**
     * @param Alaress_Date_Format $publishedAt
     * @return static
     */
    public function setPublishedAt(Alaress_Date_Format $publishedAt)
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPublished()
    {
        return ($this->publishedAt instanceof Alaress_Date_Format && !$this->publishedAt->isNull());
    }

    /**
     * @return bool
     */
    public function isDraft()
    {
        return !$this->isPublished();
    }

    /**
     * @return boolean
     */
    public function isValid()
    {
        return $this->valid;
    }

    /**
     * @deprecated Mapper method only
     * @return bool
     */
    public function getValid()
    {
        return $this->isValid();
    }

    /**
     * @param boolean $valid
     * @return static
     */
    public function setValid($valid)
    {
        $this->valid = $valid;
        return $this;
    }
}
