<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;

use Plagscan_Document;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\File;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\User;

/**
 * A single file within a student's submission to a due work.
 */
class Model implements IFile
{
    use StorableTrait;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var bool
     */
    protected $editableInline;

    /**
     * @var Plagscan_Document
     */
    protected $plagscan;

    /**
     * @var File
     */
    protected $submission;

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
     * Creation date is inferred from submission's creation date.
     */
    public function getCreatedAt()
    {
        return $this->getSubmission()->getCreatedAt();
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
     * @return Plagscan_Document
     */
    public function getPlagscan()
    {
        return $this->plagscan;
    }

    /**
     * @param Plagscan_Document $plagscan
     * @return static
     */
    public function setPlagscan($plagscan)
    {
        $this->plagscan = $plagscan;
        return $this;
    }

    /**
     * @return File
     */
    public function getSubmission()
    {
        return $this->submission;
    }

    /**
     * @param File $submission
     * @return static
     */
    public function setSubmission($submission)
    {
        $this->submission = $submission;
        return $this;
    }
}
