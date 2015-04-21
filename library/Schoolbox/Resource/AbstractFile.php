<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;

use Alaress_Date_Format;
use Schoolbox\File\Mimetype;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\File\StorableTrait;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;

/**
 * Class AbstractFile
 *
 * Provides common file-based functionality for files stored in a directory structure (ie. can have an IFolder parent)
 *
 * @package Schoolbox\Resource
 * @method AbstractFile setHash($hash)
 * @method AbstractFile setMimetype(Mimetype $mimetype)
 * @method AbstractFile setSize($size)
 */
abstract class AbstractFile implements IFile, ISoftDeletable
{
    use StorableTrait;

    /** @var int */
    protected $id;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $title;

    /** @var \Schoolbox\User */
    protected $owner;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $deletedAt;

    /** @var IFolder */
    protected $folder;


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
     * @return IFolder
     */
    public function getFolder()
    {
        return $this->folder;
    }


    /**
     * @param IFolder $folder
     * @return self
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
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
     * @return bool
     */
    public function isDeleted()
    {
        return $this->deletedAt instanceof Alaress_Date_Format
            && !$this->getDeletedAt()->isNull()
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
     * Returns the size on disk of the file
     *
     * @return int
     */
    public function getSize()
    {
        return $this->getStorage()
                    ->getSize();
    }


    /**
     * @return string
     */
    public function getHash()
    {
        return $this->getStorage()
                    ->getHash();
    }


    /**
     * @return Alaress_Date_Format
     */
    public function getLastAccessed()
    {
        return $this->getStorage()
                    ->getLastAccessed();
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