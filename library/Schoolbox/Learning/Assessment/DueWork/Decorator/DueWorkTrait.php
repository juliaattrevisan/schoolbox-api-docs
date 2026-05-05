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

use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

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
     *@var int
     */
    protected $instructorMarkType;

    /**
     * @var int
     */
    protected $gradingScheme;

    /**
     * @var int
     */
    protected $instructorGradingScheme;


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
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var float
     */
    protected $instructorMaxMark;

    /**
     * @var bool
     */
    private $similarityCheckEnabled = false;

    /**
     * @var bool
     */
    protected bool $enableWorksheet = false;

    /**
     * @var string|null
     */
    protected ?string $worksheetTemplate = null;

    /**
     * @var bool
     */
    protected bool $enableOffline = false;

    /**
     * @var bool
     */
    protected bool $enableText = false;

    /**
     * @var bool
     */
    protected bool $enableFiles = false;

    /**
     * @var bool
     */
    protected bool $enablePolicy = false;

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
    protected function getInstructorMarkType()
    {
        return $this->instructorMarkType;
    }

    /**
     * @param int $instructorMarkType
     * @return $this
     */
    protected function setInstructorMarkType($instructorMarkType)
    {
        $this->instructorMarkType = $instructorMarkType;
        return $this;
    }

    /**
     * @return int|null
     */
    protected function getGradingScheme(): ?int
    {
        return $this->gradingScheme;
    }

    /**
     * @param int|null $gradingScheme
     * @return static
     */
    protected function setGradingScheme(?int $gradingScheme): static
    {
        $this->gradingScheme = $gradingScheme;
        return $this;
    }

    /**
     * @return int|null
     */
    protected function getInstructorGradingScheme(): ?int
    {
        return $this->instructorGradingScheme;
    }

    /**
     * @param int|null $instructorGradingScheme
     * @return static
     */
    protected function setInstructorGradingScheme(?int $instructorGradingScheme): static
    {
        $this->instructorGradingScheme = $instructorGradingScheme;
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
     * @return float|null
     */
    protected function getInstructorMaxMark()
    {
        return $this->instructorMaxMark;
    }

    /**
     * @param float|null $instructorMaxMark
     */
    protected function setInstructorMaxMark($instructorMaxMark)
    {
        $this->instructorMaxMark = $instructorMaxMark;
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

    /**
     * @param bool $enableWorksheet
     * @return $this
     */
    public function setEnableWorksheet(bool $enableWorksheet): self
    {
        $this->enableWorksheet = $enableWorksheet;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableWorksheet(): bool
    {
        return $this->enableWorksheet;
    }

    /**
     * @param string|null $worksheetTemplate
     * @return $this
     */
    public function setWorksheetTemplate(?string $worksheetTemplate): self
    {
        $this->worksheetTemplate = $worksheetTemplate;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getWorksheetTemplate(): ?string
    {
        return $this->worksheetTemplate;
    }

    /**
     * @param bool $enableOffline
     * @return $this
     */
    public function setEnableOffline(bool $enableOffline): self
    {
        $this->enableOffline = $enableOffline;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableOffline(): bool
    {
        return $this->enableOffline;
    }

    /**
     * @param bool $enableText
     * @return $this
     */
    public function setEnableText(bool $enableText): self
    {
        $this->enableText = $enableText;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableText(): bool
    {
        return $this->enableText;
    }

    /**
     * @param bool $enableFiles
     * @return $this
     */
    public function setEnableFiles(bool $enableFiles): self
    {
        $this->enableFiles = $enableFiles;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnableFiles(): bool
    {
        return $this->enableFiles;
    }

    /**
     * @param bool $enablePolicy
     * @return $this
     */
    public function setEnablePolicy(bool $enablePolicy): self
    {
        $this->enablePolicy = $enablePolicy;
        return $this;
    }

    /**
     * @return bool
     */
    public function getEnablePolicy(): bool
    {
        return $this->enablePolicy;
    }
}
