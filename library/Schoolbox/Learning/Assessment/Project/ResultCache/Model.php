<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Project\ResultCache;

use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

class Model implements ISoftDeletable
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;
    use ModelTrait\DeletedAtDate;

    /**
     * The identifier for the project
     * It's specifically an int so you can skip hydration
     * to obtain the important parts: the marks
     *
     * In the interest of performance and speed:
     * the sheer annoyance of having to obtain a project
     * model, fully hydrated etc. should be deferred to
     * usage of Assessment MapperProxy instead and only
     * when you actually needed that.
     *
     * @var int
     */
    private int $projectId;


    /**
     * The learner who got marked
     */
    private User $user;


    /**
     * The person who marked $user's project
     *
     * @var User|null
     */
    private ?User $marker = null;


    /**
     * A nice text string for humans to read; equivalent to $normalisedMark
     * for the various Mark Types etc.
     *
     * @var string|null
     */
    private ?string $humanReadableMark;


    /**
     * A float between 0.00 and 100.00 that informs
     * the human-readable equivalent
     *
     * @var float|null
     */
    private ?float $normalisedMark;


    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }


    /**
     * @param User $user
     * @return $this
     */
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }


    /**
     * @return int
     */
    public function getProjectId(): int
    {
        return $this->projectId;
    }


    /**
     * @param int $projectId
     * @return Model
     */
    public function setProjectId(int $projectId): self
    {
        $this->projectId = $projectId;
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
     * @return Model
     */
    public function setHumanReadableMark(?string $humanReadableMark): self
    {
        $this->humanReadableMark = $humanReadableMark;
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
     * @return Model
     */
    public function setNormalisedMark(?float $normalisedMark): self
    {
        $this->normalisedMark = $normalisedMark;
        return $this;
    }
}
