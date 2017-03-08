<?php
/**
 * Extends a standard schoolbox file into a video file
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
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

    /**
    * Creates object and sets up sub-objects.
    */
    public function __construct()
    {
        $this->setConfig(new ConfigLoader());
    }

    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getFileLocation()
    {
        if (is_readable($this->getVideoLocation())) {
             return $this->getVideoLocation();
        } elseif (is_readable($this->getAudioLocation())) {
            return $this->getAudioLocation();
        }
        return '';
    }

    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getVideoLocation()
    {
        return $this->getConfig()->get('video_dir').'/'.$this->getHash() . '.mp4';
    }

    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getAudioLocation()
    {
        return $this->getConfig()->get('video_dir').'/'.$this->getHash() . '.mp3';
    }

    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getThumbnailLocation()
    {
        return $this->getConfig()->get('video_dir').'/'.$this->getHash() . '-64.jpg';
    }

    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getPosterLocation()
    {
        return $this->getConfig()->get('video_dir').'/'.$this->getHash() . '-poster.jpg';
    }

    /**
     * Get the full path to the image used in statuses
     *
     * @param string $type Defaults to "video", but you could pass "audio" for an audio thumbnail.
     * @return string
     */
    public function getStatusImage($type = 'video')
    {
        return "/images/core/component/$type-thumbnail-".strtolower($this->getStatus()).".gif";
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
}
