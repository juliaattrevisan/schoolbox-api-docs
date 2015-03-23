<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;


use Alaress_Date_Format;
use Schoolbox\File\Mimetype;
use Schoolbox\ISoftDeletable;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Storage\Model as Storage;

/**
 * Class Model
 *
 * @package Schoolbox\Resource\File
 * @method Model    setHash($hash)
 * @method Mimetype getMimetype()
 * @method Model    setMimetype(Mimetype $mimetype)
 * @method Model    setSize($size)
 */
class Model implements IFile, ISoftDeletable
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $title;

    /** @var string */
    protected $description;

    /** @var bool */
    protected $hidden;

    /** @var Storage */
    protected $storage;

    /** @var User */
    protected $owner;

    /** @var Folder */
    protected $folder;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $modifiedAt;

    /** @var Alaress_Date_Format */
    protected $deletedAt;


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
     * @return Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }


    /**
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deletedAt instanceof Alaress_Date_Format
            && $this->deletedAt->getUnixTimestamp() < Alaress_Date_Format::now()->getUnixTimestamp();
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
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * @param string $description
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;
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
     * @return boolean
     */
    public function isHidden()
    {
        return $this->hidden;
    }


    /**
     * @deprecated exists for Mapper limitation
     * @return bool
     */
    public function getHidden()
    {
        return $this->isHidden();
    }


    /**
     * @param boolean $hidden
     * @return self
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
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
     * @return Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }


    /**
     * @param Alaress_Date_Format $modifiedAt
     * @return self
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
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
     * Returns the full mime string for the file
     *
     * @return string
     */
    public function getMime()
    {
        return $this->getStorage()->getMimetype()->getMime();
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
     * @return Alaress_Date_Format
     */
    public function getLastAccessed()
    {
        return $this->getStorage()->getLastAccessed();
    }


    /**
     * Act as a proxy for the internal storage object
     *
     * @param string $name
     * @param array  $arguments
     * @return mixed
     */
    function __call($name, $arguments)
    {
        if (method_exists($this->storage, $name)) {
            $value = call_user_func_array([ $this->storage, $name ], $arguments);
            if ($value instanceof Storage) {
                return $this;
            }
            return $value;
        }
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $pattern = "Call to undefined method <b>%s::%s()</b> in <b>%s</b> on line <b>%d</b>, triggered";
        trigger_error(sprintf($pattern, get_called_class(), $name, $trace[1]['file'], $trace[1]['line']), E_USER_ERROR);
        return null;
    }

}