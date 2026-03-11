<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;

class Model implements ISoftDeletable
{
    use Id;
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\Reporting\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\Reporting\Model $reporting = null;


    /**
     * @var \Schoolbox\User|null
     */
    private ?\Schoolbox\User $learner = null;


    /**
     * @var \Schoolbox\User|null
     */
    private ?\Schoolbox\User $instructor = null;


    /**
     * @var float|null
     */
    private ?float $normalisedMark = null;


    /**
     * @var string|null
     */
    private ?string $humanReadableMark = null;


    /**
     * Human-readable mark seen by instructors only
     *
     * @var string|null
     */
    private ?string $instructorMark = null;


    /**
     * @var string|null
     */
    private ?string $comment = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $publishedAt = null;


    /**
     * The instructor who published this feedback
     *
     * @var int|null
     */
    private ?int $publishedBy = null;


    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\Reporting\Model|null
     */
    public function getReporting(): ?\Schoolbox\Learning\Assessment\DataModel\Reporting\Model
    {
        return $this->reporting;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\Reporting\Model|null $reporting
     * @return $this
     */
    public function setReporting(?\Schoolbox\Learning\Assessment\DataModel\Reporting\Model $reporting): self
    {
        $this->reporting = $reporting;
        return $this;
    }


    /**
     * @return \Schoolbox\User|null
     */
    public function getLearner(): ?\Schoolbox\User
    {
        return $this->learner;
    }


    /**
     * @param \Schoolbox\User|null $learner
     * @return $this
     */
    public function setLearner(?\Schoolbox\User $learner): self
    {
        $this->learner = $learner;
        return $this;
    }


    /**
     * @return \Schoolbox\User|null
     */
    public function getInstructor(): ?\Schoolbox\User
    {
        return $this->instructor;
    }


    /**
     * @param \Schoolbox\User|null $instructor
     * @return $this
     */
    public function setInstructor(?\Schoolbox\User $instructor): self
    {
        $this->instructor = $instructor;
        return $this;
    }


    /**
     * @return float|null
     */
    public function getNormalisedMark(): ?float
    {
        return $this->normalisedMark;
    }


    /**
     * @param float|null $normalisedMark
     * @return $this
     */
    public function setNormalisedMark(?float $normalisedMark): self
    {
        $this->normalisedMark = $normalisedMark;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHumanReadableMark(): ?string
    {
        return $this->humanReadableMark;
    }


    /**
     * @param string|null $humanReadableMark
     * @return $this
     */
    public function setHumanReadableMark(?string $humanReadableMark): self
    {
        $this->humanReadableMark = $humanReadableMark;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getHumanReadableInstructorMark(): ?string
    {
        return $this->instructorMark;
    }


    /**
     * @param string|null $instructorMark
     * @return $this
     */
    public function setHumanReadableInstructorMark(?string $instructorMark): self
    {
        $this->instructorMark = $instructorMark;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getComment(): ?string
    {
        return $this->comment;
    }


    /**
     * @param string|null $comment
     * @return $this
     */
    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getPublishedAt(): ?\Alaress_Date_Format
    {
        return $this->publishedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $publishedAt
     * @return $this
     */
    public function setPublishedAt(?\Alaress_Date_Format $publishedAt): self
    {
        $this->publishedAt = $publishedAt;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getPublishedBy(): ?int
    {
        return $this->publishedBy;
    }


    /**
     * @param int|null $publishedBy
     * @return $this
     */
    public function setPublishedBy(?int $publishedBy): self
    {
        $this->publishedBy = $publishedBy;
        return $this;
    }
}
