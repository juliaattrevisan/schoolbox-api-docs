<?php
/**
 * Model for teacher returns for student submissions into submission boxes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox;

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Model as StorageModel;
use Schoolbox\User;

class ReturnedWork implements ISoftDeletable
{

    /**
     * @var int
     */
    protected $id;

    /**
     * @var SubmissionBox
     */
    protected $submission_box;

    /**
     * @var User
     */
    protected $marker;

    /**
     * @var User
     */
    protected $owner;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var string
     */
    protected $mark;

    /**
     * @var float
     */
    protected $normalized_mark;

    /**
     * Is this a confirmation of an existing submission?
     * If it is, it probably doesn't show in returns and is only for setting a
     * default mark on a submission.
     *
     * @var bool
     */
    protected $isConfirmationOfSubmission = false;

    /**
     * @var \Alaress_Date_Format
     */
    protected $submitted;

    /**
     * @var \Alaress_Date_Format
     */
    protected $returned;

    /**
     * Whether this returned work item is softdeleted or not
     *
     * @var
     */
    protected $deleted;

    /**
     * Optional crocodoc uuid
     *
     * @var null
     */
    protected $uuid = null;

    /** @var IStorage */
    protected $storage;


    /**
     * Creates object and sets up related objects.
     */
    public function __construct()
    {
        $this->setSubmissionBox(new SubmissionBox());
        $this->setOwner(new User());
        // This is meant to ensure that getStorage() always returns an IStorage,
        // not to specify that it must be a particular IStorage implementation.
        $this->setStorage(new StorageModel());
    }

    /**
     * Calculates a gradient (from 0 to 10) from the normalized mark for this
     * return.
     *
     * @return int
     */
    public function getGradientFromNormalizedMark()
    {
        return max(0, min(10, (int)($this->getNormalizedMark() / 10)));
    }

    /**
     * @param  int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
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
     * @param  \Schoolbox\Assessment\Component\SubmissionBox $submission_box
     *
     * @return $this
     */
    public function setSubmissionBox($submission_box)
    {
        $this->submission_box = $submission_box;
        return $this;
    }

    /**
     * @return \Schoolbox\Assessment\Component\SubmissionBox
     */
    public function getSubmissionBox()
    {
        return $this->submission_box;
    }

    /**
     * The person who the mark is for
     * @param  \Schoolbox\User $owner
     *
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * The person who the mark is for
     * @return \Schoolbox\User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * The person who marked the work
     * @param \Schoolbox\User $marker
     *
     * @return $this
     */
    public function setMarker($marker)
    {
        $this->marker = $marker;
        return $this;
    }

    /**
     * The person who marked the work
     * @return \Schoolbox\User
     */
    public function getMarker()
    {
        return $this->marker;
    }



    /**
     * Gets the filename for the attached returned work
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->getStorage()->getFilename();
    }

    /**
     * Gets the file size for the attached returned work
     *
     * @return string
     * @deprecated
     */
    public function getFilesize()
    {
        return $this->getStorage()->getSize();
    }

    public function getMimeType() {
        return $this->getStorage()->getMimeType();
    }



    /**
     * @param  \Alaress_Date_Format $submitted
     *
     * @return $this
     */
    public function setSubmitted($submitted)
    {
        $this->submitted = $submitted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getSubmitted()
    {
        return $this->submitted;
    }

    /**
     * @param  string $comment
     *
     * @return $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * @param  string $mark
     *
     * @return $this
     */
    public function setMark($mark)
    {
        $this->mark = $mark;
        return $this;
    }

    /**
     * @return string
     */
    public function getMark()
    {
        return $this->mark;
    }

    /**
     * @param  float $normalized_mark
     *
     * @return $this
     */
    public function setNormalizedMark($normalized_mark)
    {
        $this->normalized_mark = $normalized_mark;
        return $this;
    }

    /**
     * @return float
     */
    public function getNormalizedMark()
    {
        return $this->normalized_mark;
    }

    /**
     * @param  \Alaress_Date_Format $returned
     *
     * @return $this
     */
    public function setReturned($returned)
    {
        $this->returned = $returned;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getReturned()
    {
        return $this->returned;
    }

    /**
     * Set deleted
     *
     * @param mixed $deleted
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    }

    /**
     * Get deleted
     *
     * @return mixed
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Whether or not this returned work is softdeleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        if ($this->getDeleted()) {
            return true;
        }

        return false;
    }

    /* Getters/Setters
     */

    /**
     * Set uuid
     *
     * @param null $uuid
     */
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Get uuid
     *
     * @return null
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return boolean
     */
    public function getIsConfirmationOfSubmission()
    {
        return $this->isConfirmationOfSubmission;
    }

    /**
     * @param  boolean $isConfirmationOfSubmission
     *
     * @return $this
     */
    public function setIsConfirmationOfSubmission($isConfirmationOfSubmission)
    {
        $this->isConfirmationOfSubmission = $isConfirmationOfSubmission;
        return $this;
    }

    /**
     * @return IStorage
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param IStorage $storage
     * @return static
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }

}
