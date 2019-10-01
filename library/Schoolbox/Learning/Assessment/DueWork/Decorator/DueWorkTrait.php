<?php
/**
 * DueWorkTrait
 *
 * Trait for DueWork-specific vars to use when populating an actual
 * DueWork instance. Note that decorators store these as contents
 * from a POST array (typically) and therefore contain only scalars
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
     * @var bool
     */
    protected $useMarkRange = false;

    /**
     * @var array|string[]
     */
    protected $attachedFiles;

    /**
     * @var string
     */
    private $selfReviewable;

    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var float
     */
    private $maxMark;

    /**
     * @var bool
     */
    private $similarityCheckEnabled = false;

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
    protected function getMarkPerCapability() : bool
    {
        return $this->markPerCapability;
    }

    /**
     * @param boolean $markPerCapability
     * @return $this
     */
    protected function setMarkPerCapability(bool $markPerCapability)
    {
        $this->markPerCapability = $markPerCapability;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getUseMarkRange()
    {
        return $this->useMarkRange;
    }

    /**
     * @param boolean $useMarkRange
     * @return $this
     */
    protected function setUseMarkRange($useMarkRange)
    {
        $this->useMarkRange = $useMarkRange;
        return $this;
    }

    /**
     * @return float|null
     */
    protected function getMaxMark()
    {
        return $this->maxMark;
    }

    /**
     * @param float|null $maxMark
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
     * @return null|string
     */
    public function getSelfReviewable(): ?string
    {
        return $this->selfReviewable;
    }

    /**
     * @param null|string $selfReviewable
     * @return $this
     */
    public function setSelfReviewable(?string $selfReviewable): self
    {
        $this->selfReviewable = $selfReviewable;
        return $this;
    }

    /**
     * @return bool
     */
    public function getSimilarityCheckEnabled(): bool
    {
        return $this->similarityCheckEnabled;
    }

    /**
     * @param bool $similarityCheckEnabled
     * @return $this
     */
    public function setSimilarityCheckEnabled(bool $similarityCheckEnabled): self
    {
        $this->similarityCheckEnabled = $similarityCheckEnabled;
        return $this;
    }
}
