<?php
/**
 * DueWorkTrait
 *
 * Trait for DueWork-specific vars to use when populating an actual
 * DueWork instance. Note that decorators store these as contents
 * from a POST array (typically) and therefore contain only scalars
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
    protected $useRubric = false;

    /**
     * @var array|null
     */
    protected $rubricData;

    /**
     * @var bool
     */
    protected $markPerCapability = false;

    /**
     * @var array|string[]
     */
    protected $attachedFiles;

    /**
     * @var bool
     */
    protected $selfAssessable;

    /**
     * @var bool
     */
    protected $selfReflectable;

    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var $int
     */
    private $maxMark;

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
    protected function getUseRubric()
    {
        return $this->useRubric;
    }

    /**
     * @param boolean $usesRubric
     * @return $this
     */
    protected function setUseRubric($usesRubric)
    {
        $this->useRubric = $usesRubric;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getMarkPerCapability()
    {
        return $this->markPerCapability;
    }

    /**
     * @param boolean $markPerCapability
     * @return $this
     */
    protected function setMarkPerCapability($markPerCapability)
    {
        $this->markPerCapability = $markPerCapability;
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

    /**
     * @return array|null $rubricData
     */
    protected function getRubricData()
    {
        return $this->rubricData;
    }

    /**
     * @param array|null $rubricData
     * @return static
     */
    protected function setRubricData(array $rubricData = null)
    {
        $this->rubricData = $rubricData;
        return $this;
    }

    /**
     * @return array|string[]
     */
    protected function getAttachedFiles()
    {
        return $this->attachedFiles ?: [];
    }

    /**
     * @param array|string[] $attachedFiles
     * @return $this
     */
    protected function setAttachedFiles($attachedFiles)
    {
        $this->attachedFiles = $attachedFiles;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getSelfAssessable()
    {
        return $this->selfAssessable;
    }

    /**
     * @param boolean $selfAssessable
     * @return $this
     */
    protected function setSelfAssessable($selfAssessable)
    {
        $this->selfAssessable = $selfAssessable;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSelfReflectable()
    {
        return $this->selfReflectable;
    }


    /**
     * @param bool $selfReflectable
     */
    public function setSelfReflectable(bool $selfReflectable)
    {
        $this->selfReflectable = $selfReflectable;
    }

}
