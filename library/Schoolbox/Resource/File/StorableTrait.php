<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\File;

use Schoolbox\Resource\Storage\IStorage;

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
            ->setFilename(pathinfo($basename, PATHINFO_FILENAME))
            ->setExtension(pathinfo($basename, PATHINFO_EXTENSION))
        ;
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

    /**
     * Check whether the file exists.
     *
     * @inherited from Flysystem\File
     * @return bool
     */
    public function exists()
    {
        return $this->storage->exists();
    }

    /**
     * Read the file.
     *
     * @inherited from Flysystem\File
     * @return string file contents
     */
    public function read()
    {
        return $this->storage->readStream();
    }

    /**
     * Read the file as a stream.
     *
     * @inherited from Flysystem\File
     * @return resource file stream
     */
    public function readStream()
    {
        return $this->storage->readStream();
    }
}
