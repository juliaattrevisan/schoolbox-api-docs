<?php
/**
 * Extends a standard schoolbox file into a video file
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Video;

use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;
use Schoolbox\Mapper\Traits\Model\Id;

class ResourceVideo
{
    use Id;

    /** @var string */
    protected $status;

    /** @var int */
    protected $duration;

    /** @var File\IFile */
    protected $file;

    /** @var Storage\Model */
    protected $storage;

    /** @var IConfigLoader */
    protected $config;

    /** @const string TRANSCODE Location of compressed transcode (video or audio only) version of file */
    private const TRANSCODE = '-transcode';

    /** @const string THUMBNAIL Location of small thumbnail representing compressed video */
    private const THUMBNAIL = '-thumbnail';

    /** @const string POSTER Location of poster for compressed video */
    private const POSTER = '-poster';

    /**
     * Creates object and sets up sub-objects.
     */
    public function __construct()
    {
        // TODO remove this: Queue should not extend this class
        $this->setConfig(new ConfigLoader());
    }

    /**
     * Get the full path to the image used in statuses
     *
     * @param string $type Defaults to "video", but you could pass "audio" for an audio thumbnail.
     * @return string
     */
    public function getStatusImage($type = 'video')
    {
        return "/images/core/component/$type-thumbnail-" . strtolower($this->getStatus()) . ".gif";
    }

    /**
     * Is this video currently completed?
     * @return bool
     */
    public function isComplete()
    {
        return $this->getStatus() == QueueMapper::STATUS_COMPLETED;
    }

    /**
     * Is this video currently pending?
     * @return bool
     */
    public function isPending()
    {
        return $this->getStatus() == QueueMapper::STATUS_PENDING;
    }

    /**
     * Is this video currently processing?
     * @return bool
     */
    public function isProcessing()
    {
        return $this->getStatus() == QueueMapper::STATUS_PROCESSING;
    }

    /**
     * Is this video currently failed?
     * @return bool
     */
    public function isFailed()
    {
        return $this->getStatus() == QueueMapper::STATUS_FAILED;
    }

    /**
     * Getters and setters below
     */

    /**
     * @return IConfigLoader $config
     */
    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * @param IConfigLoader $config
     * @return self
     */
    private function setConfig($config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     * @return self
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     * @return self
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return File\IFile
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param File\IFile $file
     * @return self
     */
    public function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @return Storage\Model
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * @param Storage\Model $storage
     * @return self
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getHash()
    {
        if ($this->storage instanceof Storage\Model) {
            return $this->storage->getHash();
        } else {
            return null;
        }
    }


    /**
     * Returns the path of the storage objects trancoded video or audio file
     * Note this will only exist if the object is a video/audio and it has been transcoded so test before use
     * @return string
     */
    public function getTranscodePath()
    {
        return $this->getStorage()->getPath() . self::TRANSCODE;
    }

    /**
     * Returns the path of the storage objects poster for the transcoded video
     * Note this will only exist if the object is a video and it has been transcoded so test before use
     * @return string
     */
    public function getPosterPath()
    {
        return $this->getStorage()->getPath() . self::POSTER;
    }

    /**
     * Returns the path of the storage objects thumbnail for the transcoded video
     * Note this will only exist if the object is a video and it has been transcoded so test before use
     * @return string
     */
    public function getThumbnailPath()
    {
        return $this->getStorage()->getPath() . self::THUMBNAIL;
    }
}
