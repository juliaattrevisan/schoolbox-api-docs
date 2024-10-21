<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevelModel;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * Model for due work grade groups.
 */
class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * Name of this group
     *
     * @var string
     */
    protected string $name;

    /**
     * Collection of grade levels that belong to this group
     *
     * @var Collection|GradeLevelModel[]
     */
    protected $gradeLevels;

    /**
     * @var User|null The first user to author the grade level group
     */
    protected ?User $createdBy = null;

    /**
     * @var User|null The most recent user to change the grade level group
     */
    protected ?User $updatedBy = null;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * This may return null as gradeLevels may not have been initialized yet
     *
     * @return GradeLevelModel[]|Collection|null
     */
    public function getGradeLevels(): ?Collection
    {
        return $this->gradeLevels;
    }

    /**
     * @param GradeLevelModel[]|Collection $gradeLevels
     * @return $this
     */
    public function setGradeLevels(Collection $gradeLevels): self
    {
        $this->gradeLevels = $gradeLevels;
        return $this;
    }

    /**
     * @param GradeLevelModel $level
     * @return $this
     */
    public function addGradeLevel(GradeLevelModel $level): self
    {
        // Don't add more than once
        $this->getGradeLevels()->setOffset($level->getId(), $level);
        return $this;
    }

    /**
     * User that first authored this grade level group
     *
     * @return User|null
     */
    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    /**
     * User that first authored this grade level group
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
     * User that has made the most recent changes to this grade level group
     *
     * @return User|null
     */
    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    /**
     * User that has made the changes to this grade level group
     *
     * @param ?User $updatedBy
     * @return $this
     */
    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }
}
