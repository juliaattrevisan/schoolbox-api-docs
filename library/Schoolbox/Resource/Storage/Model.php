<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Storage;


use Alaress_Date_Format;
use League\Flysystem\File;
use League\Flysystem\FilesystemInterface;
use Schoolbox\File\Mimetype;
use Schoolbox\Video\Queue;

/**
 * Class Model
 *
 * Not explicitly a "Schoolbox file entity".
 * This object ONLY represents the file on disk, and needs to be wrapped in other classes for actual Schoolbox functionality.
 *
 * @package Schoolbox\Resource\Storage
 */
class Model extends File implements IStorage
{
    /** @var string */
    protected $filename;

    /** @var string */
    protected $hash;

    /** @var Mimetype */
    protected $mimetype;

    /** @var int */
    protected $size;

    /** @var Alaress_Date_Format */
    protected $createdAt;

    /** @var Alaress_Date_Format */
    protected $lastAccessed;

    /** @var bool */
    protected $public;

    /** @var string */
    protected $extension;

    /**
     * @var Queue
     */
    protected $queue;

    public function __construct(FilesystemInterface $filesystem = null, $path = null)
    {
        parent::__construct($filesystem, $path);
        $this->setQueue(new Queue());
    }

    /**
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * @param  Queue $queue
     *
     * @return $this
     */
    public function setQueue($queue)
    {
        $this->queue = $queue;
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
     * @return string
     */
    public function getHash()
    {
        // calculate hash if not known
        if (!$this->hash) {
            $stream = $this->readStream();
            $hp     = hash_init('sha1');
            hash_update_stream($hp, $stream);
            $this->setHash(hash_final($hp));
        }
        return $this->hash;
    }


    /**
     * @param string $hash
     * @return self
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }


    /**
     * @return Mimetype
     */
    public function getMimetype()
    {
        return $this->mimetype;
    }


    /**
     * @param Mimetype $mimetype
     * @return self
     */
    public function setMimetype(Mimetype $mimetype)
    {
        $this->mimetype = $mimetype;
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
     * @return int
     */
    public function getSize()
    {
        // calculate size if not known
        if (!$this->size) {
            $this->setSize(parent::getSize());
        }
        return $this->size;
    }


    /**
     * @param int $size
     * @return self
     */
    public function setSize($size)
    {
        $this->size = $size;
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
     * @return Alaress_Date_Format
     */
    public function getLastAccessed()
    {
        return $this->lastAccessed;
    }


    /**
     * @param Alaress_Date_Format $lastAccessed
     * @return self
     */
    public function setLastAccessed($lastAccessed)
    {
        $this->lastAccessed = $lastAccessed;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isPublic()
    {
        return $this->public;
    }


    /**
     * @deprecated mapper compat
     * @return bool
     */
    public function getPublic()
    {
        return $this->isPublic();
    }

    /**
     * @param boolean $public
     * @return self
     */
    public function setPublic($public)
    {
        $this->public = $public;
        return $this;
    }


    /**
     * @return string
     */
    public function getExtension()
    {
        return $this->extension;
    }


    /**
     * @param string $extension
     * @return self
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
        return $this;
    }

}
