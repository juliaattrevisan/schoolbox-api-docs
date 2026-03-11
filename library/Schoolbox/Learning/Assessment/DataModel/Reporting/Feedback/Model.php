<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback;

use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\DataModel\AssessmentModelTrait;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model extends AbstractFeedback
{
    use Id;
    use AssessmentModelTrait;
    use CreatedAtDate;
    use CreatedBy;
    use UpdatedAtDate;
    use UpdatedBy;
    use DeletedAtDate;
    use DeletedBy;


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
     * Human-readable mark seen by instructors only
     *
     * @var string|null
     */
    private ?string $instructorMark = null;


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
