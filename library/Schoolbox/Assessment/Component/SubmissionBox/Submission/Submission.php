<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission;

use Alaress_Date_Format;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * A student's submission to a due work.
 *
 * A submission may contain multiple files.
 */
class Submission implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * The user who actually performed the create action for this submission
     *
     * @var User
     */
    private $submittedBy;

    /**
     * The user whom this submission was created for
     *
     * @var User
     */
    protected $owner;

    /** @var SubmissionBox */
    protected $submissionBox;

    /**
     * The list of files within this submission.
     *
     * @var Collection|SingleFile\Model[]
     */
    protected $files;

    /**
     * @var Alaress_Date_Format|null
     */
    private ?Alaress_Date_Format $submittedAt = null;


    /**
     * @return boolean
     */
    public function isDraft()
    {
        return !$this->submittedAt;
    }


    /**
     * Will be late if not-submitted and current time is after due date, or was submitted after due date
     *
     * @return bool
     */
    public function isLate()
    {
        $now = Alaress_Date_Format::now();
        $dueDate = $this->submissionBox->getAssessmentModel()->getDueDate();
        $isSubmitted = ! $this->isDraft();

        // Submitted after due date ?
        if ($isSubmitted && $this->submittedAt->gt($dueDate)) {
            return true;
        }

        // Or not submitted and now past due date
        return $now->gt($dueDate);
    }


    /* Getters / Setters
     */

    /**
     * @return User
     */
    public function getSubmittedBy()
    {
        return $this->submittedBy;
    }


    /**
     * @param User $submittedBy
     * @return $this
     */
    public function setSubmittedBy(User $submittedBy)
    {
        $this->submittedBy = $submittedBy;
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
     * @return Collection|SingleFile\Model[]
     */
    public function getFiles()
    {
        return $this->files;
    }

    /**
     * @param Collection|SingleFile\Model[] $files
     * @return static
     */
    public function setFiles(Collection $files)
    {
        $this->files = $files;
        return $this;
    }

    /**
     * @return Alaress_Date_Format|null
     */
    public function getSubmittedAt(): ?Alaress_Date_Format
    {
        return $this->submittedAt;
    }

    /**
     * @param Alaress_Date_Format|null $submittedAt
     *
     * @return static
     */
    public function setSubmittedAt(?Alaress_Date_Format $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }
}
