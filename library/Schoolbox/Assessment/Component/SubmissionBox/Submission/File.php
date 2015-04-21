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
use Plagscan_Document;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\File\Mimetype;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

class File implements IFile
{

    /** @var int */
    protected $id;

    /** @var IStorage */
    protected $storage;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $uuid;

    /** @var bool */
    protected $draft;

    /** @var Plagscan_Document */
    protected $plagscan;

    /** @var SubmissionBox */
    protected $submissionBox;

    /** @var bool */
    protected $marked;

    /** @var bool */
    protected $late;

    /** @var User */
    protected $owner;

    /** @var ADF */
    protected $createdAt;


    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }


    /**
     * @param string $filename
     * @return static
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
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


    /**
     * Returns the full mime string for the file
     *
     * @return string
     */
    public function getMime()
    {
        return $this->getMimetype()->getMime();
    }


    /**
     * Returns the full mime string for the file
     *
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->getStorage()->getMimetype();
    }


    /**
     * Returns the size on disk of the file
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getStorage()->getSize();
    }


    /**
     * @return string
     */
    public function getHash()
    {
        return $this->getStorage()->getHash();
    }


    /**
     * @return ADF
     */
    public function getLastAccessed()
    {
        return $this->getStorage()->getLastAccessed();
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
     * @param Plagscan_Document $plagscan
     */
    public function setPlagscan($plagscan)
    {
        $this->plagscan = $plagscan;
    }

    /**
     * @return Plagscan_Document
     */
    public function getPlagscan()
    {
        return $this->plagscan;
    }


    /**
     * @return boolean
     */
    public function isDraft()
    {
        return $this->draft;
    }


    /**
     * @deprecated only exists for mapper compatibility
     * @return boolean
     */
    public function getDraft()
    {
        return $this->isDraft();
    }


    /**
     * @param boolean $draft
     * @return static
     */
    public function setDraft($draft)
    {
        $this->draft = $draft;
        return $this;
    }


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
