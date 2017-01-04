<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Alaress_Date_Format;
use Schoolbox\Assessment\Component\SubmissionBox;
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

    /** @var int */
    protected $id;

    /** @var User */
    protected $student;

    /** @var SubmissionBox */
    protected $submissionBox;

    /** @var Criterion */
    protected $criterion;

    /** @var Descriptor */
    protected $descriptor;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var float */
    protected $mark;

    /** @var bool */
    protected $valid;


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
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
    public function setStudent($student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return Criterion
     */
    public function getCriterion()
    {
        return $this->criterion;
    }


    /**
     * @param Criterion $criterion
     * @return static
     */
    public function setCriterion($criterion)
    {
        $this->criterion = $criterion;
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
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }


    /**
     * @param Alaress_Date_Format $createdAt
     * @return static
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }


    /**
     * @return float
     */
    public function getMark()
    {
        return $this->mark;
    }


    /**
     * @param float $mark
     * @return static
     */
    public function setMark($mark)
    {
        $this->mark = $mark;
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
