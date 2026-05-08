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
    private bool $hasSelfAssessed = false;


    /**
     * @var string
     */
    private string $url;


    /**
     * @var array|string[]
     */
    private array $attachedFiles;


    /**
     * @return boolean
     */
    public function getHasSelfAssessed()
    {
        return $this->hasSelfAssessed;
    }


    /**
     * @param boolean $hasSelfAssessed
     * @return $this
     */
    public function setHasSelfAssessed($hasSelfAssessed)
    {
        $this->hasSelfAssessed = $hasSelfAssessed;
        return $this;
    }


    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }


    /**
     * @param string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }
}
