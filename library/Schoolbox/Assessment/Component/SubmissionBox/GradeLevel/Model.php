<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Model for due work grade levels.
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * Group this belongs to
     *
     * @var Group\Model
     */
    protected Group\Model $group;

    /**
     * Lower bound for grades.
     *
     * If a submission return's normalized mark is >= this value, and < any
     * other mark in the same group, the return has this label.
     *
     * @var float
     */
    protected float $lowerBound;

    /**
     * Raw grade (0-100) for this selected grade.
     *
     * If a submission return is given this grade, it is treated as having this
     * grade for averaging purposes.
     *
     * @var float
     */
    protected float $rawGrade;

    /**
     * Caption for this grade level.
     * This is used as the display value for submission returns.
     *
     * @var string
     */
    protected string $caption;

    /**
     * @var User|null
     */
    protected ?User $createdBy = null;

    /**
     * @var User|null
     */
    protected ?User $updatedBy = null;

    /**
     * @return string
     */
    public function getCaption(): string
    {
        return $this->caption;
    }

    /**
     * @param string $caption
     * @return $this
     */
    public function setCaption(string $caption): self
    {
        $this->caption = trim($caption);
        return $this;
    }

    /**
     * @return Group\Model
     */
    public function getGroup(): Group\Model
    {
        return $this->group;
    }

    /**
     * @param Group\Model $group
     * @return $this
     */
    public function setGroup(Group\Model $group): self
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @return float
     */
    public function getLowerBound(): float
    {
        return $this->lowerBound;
    }

    /**
     * @param float $lowerBound
     * @return $this
     */
    public function setLowerBound(float $lowerBound): self
    {
        $this->lowerBound = $lowerBound;
        return $this;
    }

    /**
     * @return float
     */
    public function getRawGrade(): float
    {
        return $this->rawGrade;
    }

    /**
     * @param float $rawGrade
     * @return $this
     */
    public function setRawGrade(float $rawGrade): self
    {
        $this->rawGrade = $rawGrade;
        return $this;
    }

    /**
     * User that first authored this grade level
     *
     * @param ?User $createdBy
     * @return $this
     */
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * User that first authored this grade level
     *
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * User that has made the most recent changes to this grade level group
     *
     * @param ?User $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }

    /**
     * User that has made the most recent changes to this grade level group
     *
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }
}
