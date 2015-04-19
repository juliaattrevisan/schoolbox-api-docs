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

use Schoolbox\Resource\File;

class ResourceVideo
{
    protected $id;
    protected $hash;
    protected $type;
    protected $status;
    protected $duration;
    protected $file;
    protected $fileID;
    protected $config;

    /**
    * Creates object and sets up sub-objects.
    */
    public function __construct()
    {
        $this->setConfig( new ConfigLoader() );
    }


    public function getFileLocation()
    {
        if (is_readable($this->getVideoLocation())) {
             return $this->getVideoLocation();
        } elseif (is_readable($this->getAudioLocation())) {
            return $this->getAudioLocation();
        }
    }

    public function getVideoLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '.mp4';
    }

    public function getAudioLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '.mp3';
    }

    public function getThumbnailLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '-64.jpg';
    }

    public function getPosterLocation()
    {
        return $this->config->get('video_dir').'/'.$this->getHash() . '-poster.jpg';
    }


    private function setConfig($config)
    {
        $this->config = $config;
    }

    public function setDuration($duration)
    {
        $this->duration = $duration;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getStatus()
    {
        return $this->status;
    }

    /**
    * Get the full path to the image used in statuses
    *
    * @param string $type Defaults to "video", but you could pass "audio" for
    *                     an audio thumbnail.
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

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setFile($file)
    {
        $this->file = $file;
    }

    public function getFile()
    {
        return $this->file;
    }

    /**
    * @param  mixed $id
    * @return $this
    */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
    * @return mixed
    */
    public function getId()
    {
        return $this->id;
    }

    public function setFileID($fileID)
    {
        $this->fileID = $fileID;
    }

    public function getFileID()
    {
        return $this->fileID;
    }

    /**
     * @return mixed
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param mixed $hash
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }


}
