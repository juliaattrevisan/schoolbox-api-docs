<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\User;

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

    /** @var User */
    private $student;

    /** @var Descriptor */
    private $descriptor;

    /** @var bool */
    private $valid;


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
    public function setStudent($student)
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
    public function setDescriptor($descriptor)
    {
        $this->descriptor = $descriptor;
        return $this;
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
