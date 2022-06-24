<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\File\Mimetype;

/**
 * Trait for common features of models which contain a storage file.
 */
trait StorableTrait
{
    /** @var int */
    protected $id;

    /** @var IStorage */
    protected $storage;

    /** @var string */
    protected $filename;


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
     * @return string
     */
    public function getBasename()
    {
        return $this->getFilename() . '.' . $this->getExtension();
    }

    /**
     * @param string $basename
     * @return $this
     */
    public function setBasename($basename)
    {
        return $this
            ->setFilename(mb_pathinfo($basename, PATHINFO_FILENAME))
            ->setExtension(mb_pathinfo($basename, PATHINFO_EXTENSION))
        ;
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
     * @return \Alaress_Date_Format
     */
    public function getLastAccessed()
    {
        return $this->getStorage()->getLastAccessed();
    }

    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->getStorage()->getExtension();
    }

    /**
     * @param string $extension
     * @return $this
     */
    public function setExtension($extension)
    {
        $this->getStorage()->setExtension($extension);
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
}
