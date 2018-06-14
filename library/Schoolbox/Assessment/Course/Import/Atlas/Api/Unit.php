<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use Alaress_Date_Format;

/**
 * Class Unit
 */
class Unit
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $timeFrame;

    /**
     * @var Alaress_Date_Format
     */
    private $lastUpdated;

    /**
     * @var Alaress_Date_Format
     */
    private $startDate;

    /**
     * @var Alaress_Date_Format
     */
    private $endDate;

    /**
     * @var Assessment[]
     */
    private $assessments;

    /**
     * @var Attachment[]
     */
    private $attachments;

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getTimeFrame(): ?string
    {
        return $this->timeFrame;
    }

    /**
     * @param string $timeFrame
     * @return static
     */
    public function setTimeFrame(?string $timeFrame)
    {
        $this->timeFrame = $timeFrame;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getLastUpdated(): ?Alaress_Date_Format
    {
        return $this->lastUpdated;
    }

    /**
     * @param Alaress_Date_Format $lastUpdated
     * @return static
     */
    public function setLastUpdated(?Alaress_Date_Format $lastUpdated)
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getStartDate(): ?Alaress_Date_Format
    {
        return $this->startDate;
    }

    /**
     * @param Alaress_Date_Format $startDate
     * @return static
     */
    public function setStartDate(?Alaress_Date_Format $startDate)
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getEndDate(): ?Alaress_Date_Format
    {
        return $this->endDate;
    }

    /**
     * @param Alaress_Date_Format $endDate
     * @return static
     */
    public function setEndDate(?Alaress_Date_Format $endDate)
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * @return Assessment[]
     */
    public function getAssessments(): array
    {
        return $this->assessments;
    }

    /**
     * @param Assessment[] $assessments
     * @return static
     */
    public function setAssessments(array $assessments)
    {
        $this->assessments = $assessments;
        return $this;
    }

    /**
     * @return Attachment[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param Attachment[] $attachments
     * @return static
     */
    public function setAttachments(array $attachments)
    {
        $this->attachments = $attachments;
        return $this;
    }
}
