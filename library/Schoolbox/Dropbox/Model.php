<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Dropbox;

use Alaress_Date_Format;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Class Model
 *
 * TODO consider refactoring this into the Resource\AbstractFile abstraction once merged with SB-7185
 *
 * @package Schoolbox\Dropbox
 */
class Model implements ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $title;

    /** @var Folder */
    protected $folder;

    /** @var User */
    protected $owner;

    /** @var string */
    protected $filename;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $deletedAt;

    /** @var Storage */
    protected $storage;


    /**
     * @return Alaress_Date_Format
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }


    /**
     * @param Alaress_Date_Format $createdAt
     * @return self
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
        return $this;
    }


    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }


    /**
     * @param string $filename
     * @return self
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }


    /**
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
    }


    /**
     * @param Folder $folder
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
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
     * @return self
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
     * @return self
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }


    /**
     * @return Storage
     */
    public function getStorage()
    {
        return $this->storage;
    }


    /**
     * @param Storage $storage
     * @return self
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }


    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }


    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }


    /**
     * @param Alaress_Date_Format $deletedAt
     * @return self
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }


    /**
     * Is this object deleted?
     *
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deletedAt instanceof Alaress_Date_Format
            && !$this->deletedAt->isNull()
            && $this->deletedAt->getUnixTimeStamp() < Alaress_Date_Format::now()->getUnixTimeStamp();
    }
}
