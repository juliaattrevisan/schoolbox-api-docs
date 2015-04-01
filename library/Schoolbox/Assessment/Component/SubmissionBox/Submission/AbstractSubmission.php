<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;


use Alaress_Date_Format as ADF;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\User;

abstract class AbstractSubmission implements ISubmission
{

    /** @var SubmissionBox */
    protected $submissionBox;

    /** @var bool */
    protected $marked;

    /** @var bool */
    protected $late;

    /** @var int */
    protected $id;

    /** @var User */
    protected $owner;

    /** @var ADF */
    protected $createdAt;


    /**
     * @return ADF
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }


    /**
     * @param ADF $createdAt
     * @return static
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }



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
    public function getOwner()
    {
        return $this->owner;
    }


    /**
     * @param User $owner
     * @return static
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }


    /**
     * @return SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submissionBox;
    }


    /**
     * @param SubmissionBox $submissionBox
     * @return static
     */
    public function setSubmissionBox($submissionBox)
    {
        $this->submissionBox = $submissionBox;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isLate()
    {
        return $this->late;
    }

    /**
     * @deprecated for mapper use only
     * @return bool
     */
    public function getLate()
    {
        return $this->isLate();
    }


    /**
     * @param boolean $late
     * @return static
     */
    public function setLate($late)
    {
        $this->late = $late;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isMarked()
    {
        return $this->marked;
    }


    /**
     * @deprecated for mapper use only
     * @return bool
     */
    public function getMarked()
    {
        return $this->isMarked();
    }

    /**
     * @param boolean $marked
     * @return static
     */
    public function setMarked($marked)
    {
        $this->marked = $marked;
        return $this;
    }


}