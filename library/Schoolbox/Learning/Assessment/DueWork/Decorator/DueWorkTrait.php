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

trait DueWorkTrait
{
    /**
     * @var int
     */
    private int $acceptOnline;


    /**
     * @var int
     */
    private int $markType;


    /**
     *@var int
     */
    private int $instructorMarkType;


    /**
     * @var null|int
     */
    private ?int $gradingScheme;


    /**
     * @var null|int
     */
    private ?int $instructorGradingScheme;


    /**
     * @var null|string
     */
    private ?string $selfReviewable;


    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var null|float
     */
    private ?float $maxMark;


    /**
     * Only relevant if this DueWork's MarkType is 'TotalMark'
     *
     * @var null|float
     */
    private ?float $instructorMaxMark;


    /**
     * @var bool
     */
    private bool $similarityCheckEnabled;


    /**
     * @var null|bool
     */
    private ?bool $enableWorksheet = null;


    /**
     * @var string|null
     */
    private ?string $worksheetTemplate = null;


    /**
     * @var bool|null
     */
    private ?bool $enableOffline = null;


    /**
     * @var bool|null
     */
    private ?bool $enableText = null;


    /**
     * @var bool|null
     */
    private ?bool $enableFiles = null;


    /**
     * @var bool|null
     */
    private ?bool $enablePolicy = null;


    /**
     * @return int
     */
    public function getAcceptOnline()
    {
        return $this->acceptOnline;
    }


    /**
     * @param int $acceptOnline
     * @return $this
     */
    public function setAcceptOnline($acceptOnline)
    {
        $this->acceptOnline = $acceptOnline;
        return $this;
    }


    /**
     * @return int
     */
    public function getMarkType()
    {
        return $this->markType;
    }


    /**
     * @param int $markType
     * @return $this
     */
    public function setMarkType($markType)
    {
        $this->markType = $markType;
        return $this;
    }


    /**
     * @return int
     */
    public function getInstructorMarkType()
    {
        return $this->instructorMarkType;
    }


    /**
     * @param int $instructorMarkType
     * @return $this
     */
    public function setInstructorMarkType($instructorMarkType)
    {
        $this->instructorMarkType = $instructorMarkType;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getGradingScheme(): ?int
    {
        return $this->gradingScheme;
    }


    /**
     * @param int|null $gradingScheme
     * @return static
     */
    public function setGradingScheme(?int $gradingScheme): static
    {
        $this->gradingScheme = $gradingScheme;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getInstructorGradingScheme(): ?int
    {
        return $this->instructorGradingScheme;
    }


    /**
     * @param int|null $instructorGradingScheme
     * @return static
     */
    public function setInstructorGradingScheme(?int $instructorGradingScheme): static
    {
        $this->instructorGradingScheme = $instructorGradingScheme;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getMaxMark()
    {
        return $this->maxMark;
    }


    /**
     * @param float|null $maxMark
     */
    public function setMaxMark($maxMark)
    {
        $this->maxMark = $maxMark;
    }


    /**
     * @return float|null
     */
    public function getInstructorMaxMark()
    {
        return $this->instructorMaxMark;
    }


    /**
     * @param float|null $instructorMaxMark
     */
    public function setInstructorMaxMark($instructorMaxMark)
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
     * @return bool|null
     */
    public function getEnableWorksheet(): ?bool
    {
        return $this->enableWorksheet;
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
     * @return string|null
     */
    public function getWorksheetTemplate(): ?string
    {
        return $this->worksheetTemplate;
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
     * @return bool|null
     */
    public function getEnableOffline(): ?bool
    {
        return $this->enableOffline;
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
     * @return bool|null
     */
    public function getEnableText(): ?bool
    {
        return $this->enableText;
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
     * @return bool|null
     */
    public function getEnableFiles(): ?bool
    {
        return $this->enableFiles;
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
     * @return bool|null
     */
    public function getEnablePolicy(): ?bool
    {
        return $this->enablePolicy;
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
}
