<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\DifferentiationProfile;

use Schoolbox\GenericCollection;
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
    private ?GenericCollection $adjustment_category = null;
    private ?string $adjustment_category_ids = null;
    private ?GenericCollection $adjustment_level = null;
    private ?string $adjustment_level_ids = null;
    private ?string $overview = null;
    private ?string $interests_strengths = null;
    private ?string $needs_challenges = null;
    private ?string $strategies_adjustments = null;
    private ?string $goals = null;

    /** @var User $student */
    protected User $student;

    public function __construct()
    {
        $this->adjustment_category = new GenericCollection(AdjustmentCategories\Model::class);
        $this->adjustment_level = new GenericCollection(AdjustmentLevels\Model::class);
    }

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
     * @return GenericCollection|null
     */
    public function getAdjustmentCategory(): ?GenericCollection
    {
        return $this->adjustment_category;
    }

    /**
     * Set adjustment category
     *
     * @param GenericCollection|null $adjustment_category
     * @return self
     */
    public function setAdjustmentCategory(?GenericCollection $adjustment_category): self
    {
        $this->adjustment_category = $adjustment_category;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAdjustmentCategoryIds(): ?string
    {
        return $this->adjustment_category_ids;
    }

    /**
     * @param string|null $adjustment_category_ids
     * @return self
     */
    public function setAdjustmentCategoryIds(?string $adjustment_category_ids): self
    {
        $this->adjustment_category_ids = $adjustment_category_ids;
        return $this;
    }

    /**
     * Get adjustment level
     *
     * @return GenericCollection|null
     */
    public function getAdjustmentLevel(): ?GenericCollection
    {
        return $this->adjustment_level;
    }

    /**
     * Set adjustment level
     *
     * @param GenericCollection|null $adjustment_level
     * @return self
     */
    public function setAdjustmentLevel(?GenericCollection $adjustment_level): self
    {
        $this->adjustment_level = $adjustment_level;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAdjustmentLevelIds(): ?string
    {
        return $this->adjustment_level_ids;
    }

    /**
     * @param string|null $adjustment_level_ids
     * @return self
     */
    public function setAdjustmentLevelIds(?string $adjustment_level_ids): self
    {
        $this->adjustment_level_ids = $adjustment_level_ids;
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
