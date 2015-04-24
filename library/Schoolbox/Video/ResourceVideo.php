<?php
/**
 * Extends a standard schoolbox file into a video file
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Video;

use Schoolbox\ConfigLoader;

use Schoolbox\IConfigLoader;
use Schoolbox\Resource\File;
use Schoolbox\Resource\Storage;

class ResourceVideo
{
    /** @var int */
    protected $id;

    /** @var string */
    protected $hash;

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
        $this->setConfig( new ConfigLoader() );
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
        return $this->config->get('video_dir').'/'.$this->getHash() . '.mp4';
    }


    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getAudioLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '.mp3';
    }


    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getThumbnailLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '-64.jpg';
    }


    /**
     * @deprecated this needs to switch to the Flysystem paradigm
     * @return string
     */
    public function getPosterLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '-poster.jpg';
    }


    /**
     * @param IConfigLoader $config
     */
    private function setConfig($config)
    {
        $this->config = $config;
    }


    /**
     * @param int $duration
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
    }


    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }


    /**
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }


    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Get the full path to the image used in statuses
     *
     * @param string $type Defaults to "video", but you could pass "audio" for an audio thumbnail.
     *
     * @return string
     */
    public function getStatusImage($type = 'video')
    {
        return "/images/core/component/$type-thumbnail-".strtolower($this->getStatus()).".gif";
    }

    /**
    * Is this video processing completed?
    *
    * @return bool
    */
    public function isComplete()
    {
        if ($this->getStatus() == QueueMapper::STATUS_COMPLETED) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * @param File\IFile $file
     */
    public function setFile($file)
    {
        $this->file = $file;
    }


    /**
     * @return File\IFile
     */
    public function getFile()
    {
        return $this->file;
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
     */
    public function setStorage($storage)
    {
        $this->storage = $storage;
    }



    /**
    * @param  int $id
    * @return self
    */
    public function setId($id)
    {
        $this->id = $id;
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
     * @return string
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param string $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }


}
