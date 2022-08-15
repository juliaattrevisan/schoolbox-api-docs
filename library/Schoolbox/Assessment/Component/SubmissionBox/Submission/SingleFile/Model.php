<?php

/**
 * A File that was uploaded as part of a Student's Submission
 * for a particular Due Work
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Turnitin;

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
     * @var Submission|DueWork\Response\Model
     */
    protected $submission;

    /**
     * @var bool
     */
    protected $editableInline;

    /**
     * @var Plagscan
     */
    protected $plagscan;

    /** @var Kami\Model */
    private $kami;


    /**
     * A Turnitin Report model, optionally null
     *
     * @var Turnitin\File\Model|null
     */
    private ?Turnitin\File\Model $turnitin = null;


    public function __clone()
    {
        // Robustly protect against scalar nulls
        if (is_object($this->getCreatedAt())) {
            $this->setCreatedAt(clone $this->getCreatedAt());
        }
        if (is_object($this->getUpdatedAt())) {
            $this->setUpdatedAt(clone $this->getUpdatedAt());
        }
        if (is_object($this->getDeletedAt())) {
            $this->setDeletedAt(clone $this->getDeletedAt());
        }
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
     * @return null|Plagscan
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
     * @return Turnitin\File\Model|null
     */
    public function getTurnItIn(): ?Turnitin\File\Model
    {
        return $this->turnitin;
    }


    /**
     * @param Turnitin\File\Model|null $turnitin
     * @return $this
     */
    public function setTurnItIn(?Turnitin\File\Model $turnitin): self
    {
        $this->turnitin = $turnitin;
        return $this;
    }


    /**
     * @return Submission|DueWork\Response\Model
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
     * @param Submission|DueWork\Response\Model $submission DO NOT DECLARE TYPE (unless common interface)
     * @return static
     */
    public function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }


    /**
     * @return null|Kami\Model
     */
    public function getKami(): ?Kami\Model
    {
        return $this->kami;
    }


    /**
     * @param null|Kami\Model $kami
     * @return $this|self
     */
    public function setKami(?Kami\Model $kami): self
    {
        $this->kami = $kami;
        return $this;
    }
}
