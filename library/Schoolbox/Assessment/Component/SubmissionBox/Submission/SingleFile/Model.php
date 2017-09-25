<?php
/**
 * A File that was uploaded as part of a Student's Submission
 * for a particular Due Work
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Model as Plagscan;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\DueWork;

/**
 * A single file within a student's submission to a due work.
 */
class Model implements IFile, ISoftDeletable
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Because this object is used in both the domain and data graphs,
     * it will point to either the domain or data version of the Student Submission
     *
     * @var Submission|DueWork\Submission\Model
     */
    protected $submission;

    /**
     * @var string
     */
    protected $uuid;


    /**
     * @var bool
     */
    protected $editableInline;

    /**
     * @var Plagscan
     */
    protected $plagscan;


    public function __clone()
    {
        $this->setCreatedAt(clone $this->getCreatedAt());
        $this->setUpdatedAt(clone $this->getUpdatedAt());
        $this->setDeletedAt(clone $this->getDeletedAt());
    }


    /**
     * Owner is inferred from the submission's owner.
     *
     * @return User
     */
    public function getOwner()
    {
        return $this->getSubmission()->getOwner();
    }

    /**
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @param string $uuid
     * @return static
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
        return $this;
    }


    /**
     * @return string
     */
    public function getCrocodocId()
    {
        return $this->getUuid();
    }


    /**
     * @param string $crocodocId
     * @return self
     */
    public function setCrocodocId($crocodocId)
    {
        return $this->setUuid($crocodocId);
    }


    /**
     * @return boolean
     */
    public function getEditableInline()
    {
        return $this->editableInline;
    }

    /**
     * @param boolean $editableInline
     * @return static
     */
    public function setEditableInline($editableInline)
    {
        $this->editableInline = $editableInline;
        return $this;
    }

    /**
     * @return Plagscan
     */
    public function getPlagscan()
    {
        return $this->plagscan;
    }

    /**
     * @param Plagscan $plagscan
     * @return static
     */
    public function setPlagscan($plagscan)
    {
        $this->plagscan = $plagscan;
        return $this;
    }

    /**
     * @return Submission|DueWork\Submission\Model
     */
    public function getSubmission()
    {
        return $this->submission;
    }


    /**
     * This Submission File has an attached Plagscan result document
     *
     * @return bool
     */
    public function hasPlagscan()
    {
        return $this->getPlagscan() !== null;
    }


    /**
     * Wrap up hasPlagscan() logic here, to save you from doing it everywhere
     *
     * @return string
     */
    public function findPlagscanScore()
    {
        if ($this->hasPlagscan()) {
            return (string)$this->getPlagscan()->getScore();
        }

        /* TODO: Probably check for returning a status of some sort first
         */

        return '';
    }

    /**
     * @param Submission|DueWork\Submission\Model $submission DO NOT DECLARE TYPE (unless common interface)
     * @return static
     */
    public function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }
}
