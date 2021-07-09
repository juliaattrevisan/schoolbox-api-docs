<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Schoolbox\GenericCollection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Class Model
 *
 * @package Schoolbox\Learning\Badge\Category
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;


    /**
     * These consts are used with frontend to decide whether users
     * chose an icon or uploaded an image for their badge avatar
     */
    public const AVATAR_ICON = 'icon';
    public const AVATAR_IMAGE = 'image';


    /**
     * Constants for when an achievement or goal awarded from a badge will
     * expire.
     */
    public const EXPIRY_NEVER                    = null;
    public const EXPIRY_DAY                      = 1;
    public const EXPIRY_WEEK                     = 7;
    public const EXPIRY_FORTNIGHT                = 14;
    public const EXPIRY_MONTH                    = 31;
    public const EXPIRY_QUARTER_YEAR             = 92;
    public const EXPIRY_HALF_YEAR                = 183;
    public const EXPIRY_YEAR                     = 365;
    public const VALID_ACHIEVEMENT_EXPIRY_VALUES = [
        self::EXPIRY_NEVER,
        self::EXPIRY_DAY,
        self::EXPIRY_WEEK,
        self::EXPIRY_FORTNIGHT,
        self::EXPIRY_MONTH,
        self::EXPIRY_QUARTER_YEAR,
        self::EXPIRY_HALF_YEAR,
        self::EXPIRY_YEAR,
    ];
    public const VALID_GOAL_EXPIRY_VALUES        = [
        self::EXPIRY_NEVER,
        self::EXPIRY_DAY,
        self::EXPIRY_WEEK,
        self::EXPIRY_FORTNIGHT,
        self::EXPIRY_MONTH,
        self::EXPIRY_QUARTER_YEAR,
        self::EXPIRY_HALF_YEAR,
        self::EXPIRY_YEAR,
    ];


    /**
     * @var string
     */
    private string $name;


    /**
     * @var string
     */
    private string $body;


    /**
     * Optional icon file associated with this Badge
     *
     * @var string|null
     */
    private ?string $icon = null;


    /**
     * Optional image associated with this Badge
     *
     * @var Storage\Model|null
     */
    private ?Storage\Model $image = null;


    /**
     * Flag to control if a badge can be set as a goal
     */
    private bool $allowAsGoal;


    /**
     * The Badge's Category
     *
     * @var Category\Model
     */
    private Category\Model $category;


    /**
     * In how many days will an achievement created from this badge expire?
     */
    private ?int $achievementExpiry;


    /**
     * In how many days will a goal created from this badge expire?
     */
    private ?int $goalExpiry;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $yearLevels = null;


    /**
     * @var GenericCollection|null
     */
    private ?GenericCollection $curriculumNodes = null;


    /**
     * @var User
     */
    private User $createdBy;


    /**
     * @var User
     */
    private User $updatedBy;


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param string $name
     * @return Model
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }


    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }


    /**
     * @param string $body
     * @return Model
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }


    /**
     * @param string|null $icon
     * @return $this
     */
    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }


    /**
     * @return Storage\Model|null
     */
    public function getImage(): ?Storage\Model
    {
        return $this->image;
    }


    /**
     * @param Storage\Model|null $image
     * @return $this
     */
    public function setImage(?Storage\Model $image): self
    {
        $this->image = $image;
        return $this;
    }


    /**
     * @return bool
     */
    public function getAllowAsGoal(): bool
    {
        return $this->allowAsGoal;
    }


    /**
     * @param bool $allowAsGoal
     * @return static
     */
    public function setAllowAsGoal(bool $allowAsGoal): self
    {
        $this->allowAsGoal = $allowAsGoal;
        return $this;
    }


    /**
     * @return Category\Model
     */
    public function getCategory(): Category\Model
    {
        return $this->category;
    }


    /**
     * @param Category\Model $category
     * @return $this
     */
    public function setCategory(Category\Model $category): self
    {
        $this->category = $category;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getAchievementExpiry(): ?int
    {
        return $this->achievementExpiry;
    }


    /**
     * @param int|null $achievementExpiry
     * @return $this
     * @throws UnexpectedValueException if `$achievementExpiry` isn't in
     *                                  `self::VALID_ACHIEVEMENT_EXPIRY_VALUES`
     */
    public function setAchievementExpiry(?int $achievementExpiry): self
    {
        if (!in_array($achievementExpiry, self::VALID_ACHIEVEMENT_EXPIRY_VALUES, true)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Achievement expiry must be one of: [%s], received %d instead',
                    implode(',', self::VALID_ACHIEVEMENT_EXPIRY_VALUES),
                    $achievementExpiry,
                )
            );
        }
        $this->achievementExpiry = $achievementExpiry;
        return $this;
    }


    /**
     * @return int|null
     */
    public function getGoalExpiry(): ?int
    {
        return $this->goalExpiry;
    }


    /**
     * @param int|null $goalExpiry
     * @return static
     */
    public function setGoalExpiry(?int $goalExpiry): self
    {
        if (!in_array($goalExpiry, self::VALID_GOAL_EXPIRY_VALUES, true)) {
            throw new UnexpectedValueException(
                sprintf(
                    'Goal expiry must be one of: [%s], received %d instead',
                    implode(',', self::VALID_GOAL_EXPIRY_VALUES),
                    $goalExpiry,
                )
            );
        }
        $this->goalExpiry = $goalExpiry;
        return $this;
    }


    /**
     * @return GenericCollection|\Schoolbox\YearLevel\Model[]|null
     */
    public function getYearLevels(): ?GenericCollection
    {
        return $this->yearLevels;
    }


    /**
     * @param GenericCollection $yearLevels
     * @return $this
     */
    public function setYearLevels(GenericCollection $yearLevels): self
    {
        $yearLevels->assertCollectionOf(\Schoolbox\YearLevel\Model::class);
        $this->yearLevels = $yearLevels;
        return $this;
    }


    /**
     * @return GenericCollection|null
     */
    public function getCurriculumNodes(): ?GenericCollection
    {
        return $this->curriculumNodes;
    }


    /**
     * @param GenericCollection $curriculumNodes
     * @return Model
     */
    public function setCurriculumNodes(GenericCollection $curriculumNodes): self
    {
        $this->curriculumNodes = $curriculumNodes;
        return $this;
    }


    /**
     * @return User
     */
    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }


    /**
     * @param User $createdBy
     * @return $this
     *
     */
    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }


    /**
     * @return User
     */
    public function getUpdatedBy(): User
    {
        return $this->updatedBy;
    }


    /**
     * @param User $updatedBy
     * @return $this
     */
    public function setUpdatedBy(User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
