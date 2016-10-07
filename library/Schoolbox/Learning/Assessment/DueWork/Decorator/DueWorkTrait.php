<?php
/**
 * DueWorkTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Decorator;

use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

trait DueWorkTrait
{

    /**
     * @var int
     */
    protected $acceptOnline = DueWork::ACCEPT_ONLINE_NONE;

    /**
     * @var int
     */
    protected $markType;

    /**
     * @var int
     */
    protected $gradingScheme;

    /**
     * @var bool
     */
    protected $hasRubric = false;

    /**
     * @var bool
     */
    protected $hasMarkPerCapability = false;

    /**
     * @return int
     */
    protected function getAcceptOnline()
    {
        return $this->acceptOnline;
    }

    /**
     * @param int $acceptOnline
     * @return $this
     */
    protected function setAcceptOnline($acceptOnline)
    {
        $this->acceptOnline = $acceptOnline;
        return $this;
    }

    /**
     * @return int
     */
    protected function getMarkType()
    {
        return $this->markType;
    }

    /**
     * @param int $markType
     * @return $this
     */
    protected function setMarkType($markType)
    {
        $this->markType = $markType;
        return $this;
    }

    /**
     * @return int
     */
    protected function getGradingScheme()
    {
        return $this->gradingScheme;
    }

    /**
     * @param int $gradingScheme
     * @return $this
     */
    protected function setGradingScheme($gradingScheme)
    {
        $this->gradingScheme = $gradingScheme;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasRubric()
    {
        return $this->hasRubric;
    }

    /**
     * @param boolean $hasRubric
     * @return $this
     */
    protected function setHasRubric($hasRubric)
    {
        $this->hasRubric = $hasRubric;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasMarkPerCapability()
    {
        return $this->hasMarkPerCapability;
    }

    /**
     * @param boolean $hasMarkPerCapability
     * @return $this
     */
    protected function setHasMarkPerCapability($hasMarkPerCapability)
    {
        $this->hasMarkPerCapability = $hasMarkPerCapability;
        return $this;
    }

    /**
     * @return int
     */
    protected function getMaxMark()
    {
        return $this->maxMark;
    }

    /**
     * @param int $maxMark
     */
    protected function setMaxMark($maxMark)
    {
        $this->maxMark = $maxMark;
    }
}