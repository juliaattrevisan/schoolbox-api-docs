<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\DifferentiationProfile;

use Schoolbox\ISoftDeletable;
use Schoolbox\Sequential;
use Schoolbox\Mapper\Traits\Model as ModelTraits;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use ModelTraits\Id;
    use ModelTraits\CreatedAtDate;
    use ModelTraits\UpdatedAtDate;
    use ModelTraits\DeletedAtDate;

//    private int $student_id;
    private ?int $adjustment_category = null;
    private ?int $adjustment_level = null;
    private ?string $overview = null;
    private ?string $interests_strengths = null;
    private ?string $needs_challenges = null;
    private ?string $strategies_adjustments = null;
    private ?string $goals = null;

    /** @var User $student */
    protected User $student;

//    /**
//     * Get student ID
//     *
//     * @return int
//     */
//    public function getStudentId(): int
//    {
//        return $this->student_id;
//    }
//
//    /**
//     * Set student ID
//     *
//     * @param int $student_id
//     * @return self
//     */
//    public function setStudentId(int $student_id): self
//    {
//        $this->student_id = $student_id;
//        return $this;
//    }

    /**
     * Get student
     *
     * @return User
     */
    public function getStudent(): User
    {
        return $this->student;
    }

    /**
     * Set student
     *
     * @param User $student
     * @return self
     */
    public function setStudent(User $student): self
    {
        $this->student = $student;
        return $this;
    }

    /**
     * Get adjustment category
     *
     * @return int|null
     */
    public function getAdjustmentCategory(): ?int
    {
        return $this->adjustment_category;
    }

    /**
     * Set adjustment category
     *
     * @param int|null $adjustment_category
     * @return self
     */
    public function setAdjustmentCategory(?int $adjustment_category): self
    {
        $this->adjustment_category = $adjustment_category;
        return $this;
    }

    /**
     * Get adjustment level
     *
     * @return int|null
     */
    public function getAdjustmentLevel(): ?int
    {
        return $this->adjustment_level;
    }

    /**
     * Set adjustment level
     *
     * @param int|null $adjustment_level
     * @return self
     */
    public function setAdjustmentLevel(?int $adjustment_level): self
    {
        $this->adjustment_level = $adjustment_level;
        return $this;
    }

    /**
     * Get overview
     *
     * @return string|null
     */
    public function getOverview(): ?string
    {
        return $this->overview;
    }

    /**
     * Set overview
     *
     * @param string|null $overview
     * @return self
     */
    public function setOverview(?string $overview): self
    {
        $this->overview = $overview;
        return $this;
    }

    /**
     * Get interests and strengths
     *
     * @return string|null
     */
    public function getInterestsStrengths(): ?string
    {
        return $this->interests_strengths;
    }

    /**
     * Set interests and strengths
     *
     * @param string|null $interests_strengths
     * @return self
     */
    public function setInterestsStrengths(?string $interests_strengths): self
    {
        $this->interests_strengths = $interests_strengths;
        return $this;
    }

    /**
     * Get needs and challenges
     *
     * @return string|null
     */
    public function getNeedsChallenges(): ?string
    {
        return $this->needs_challenges;
    }

    /**
     * Set needs and challenges
     *
     * @param string|null $needs_challenges
     * @return self
     */
    public function setNeedsChallenges(?string $needs_challenges): self
    {
        $this->needs_challenges = $needs_challenges;
        return $this;
    }

    /**
     * Get strategies and adjustments
     *
     * @return string|null
     */
    public function getStrategiesAdjustments(): ?string
    {
        return $this->strategies_adjustments;
    }

    /**
     * Set strategies and adjustments
     *
     * @param string|null $strategies_adjustments
     * @return self
     */
    public function setStrategiesAdjustments(?string $strategies_adjustments): self
    {
        $this->strategies_adjustments = $strategies_adjustments;
        return $this;
    }

    /**
     * Get goals
     *
     * @return string|null
     */
    public function getGoals(): ?string
    {
        return $this->goals;
    }

    /**
     * Set goals
     *
     * @param string|null $goals
     * @return self
     */
    public function setGoals(?string $goals): self
    {
        $this->goals = $goals;
        return $this;
    }
}
