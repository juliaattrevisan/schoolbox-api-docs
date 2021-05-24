<?php

/**
 * TaskTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Task\Decorator;

trait TaskTrait
{
    /**
     * @var bool
     */
    protected $hasSelfAssessed = false;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var array|string[]
     */
    protected $attachedFiles;

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
}
