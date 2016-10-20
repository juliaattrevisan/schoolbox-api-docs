<?php
/**
 * TaskTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Task\Decorator;


trait TaskTrait
{
    /**
     * @var bool
     */
    protected $hasSelfAssessed = false;

    /**
     * @var int
     */
    protected $maxMark;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var string
     */
    protected $attachedFile;

    /**
     * @return boolean
     */
    protected function getHasSelfAssessed()
    {
        return $this->hasSelfAssessed;
    }

    /**
     * @param boolean $hasSelfAssessed
     * @return $this
     */
    protected function setHasSelfAssessed($hasSelfAssessed)
    {
        $this->hasSelfAssessed = $hasSelfAssessed;
        return $this;
    }

    /**
     * @return string
     */
    protected function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    protected function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return string
     */
    public function getAttachedFile()
    {
        return $this->attachedFile;
    }

    /**
     * @param string $attachedFile
     * @return $this
     */
    public function setAttachedFile($attachedFile)
    {
        $this->attachedFile = $attachedFile;
        return $this;
    }

}