<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group;

use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Model as GradeLevelModel;
use Schoolbox\Collection;
use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Model for due work grade groups.
 */
class Model
{
    use Id;

    /**
     * Name of this group
     *
     * @var string
     */
    protected $name;

    /**
     * Collection of grade levels that belong to this group
     *
     * @var Collection|GradeLevelModel[]
     */
    protected $gradeLevels;

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
}
