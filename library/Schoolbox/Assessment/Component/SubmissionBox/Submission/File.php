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
use Schoolbox\File\Mimetype;
use Schoolbox\Resource\File\IFile;
use Schoolbox\Resource\Storage\IStorage;

class File extends AbstractSubmission implements IFile
{

    /** @var IStorage */
    protected $storage;

    /** @var string */
    protected $filename;

    /** @var string */
    protected $uuid;


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

}