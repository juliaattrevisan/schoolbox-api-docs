<?php

/**
 * Model for a single AI session (one row of the `ai_session` table).
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Session;

use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\User;

class Model
{
    use CreatedAtDate;

    /**
     * UUID primary key. Null until persisted (the mapper auto-generates it on save).
     */
    private ?string $id = null;

    private ?User $user = null;

    private ?string $feature = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    /**
     * @param string|Feature|null $feature
     */
    public function setFeature(string|Feature|null $feature): self
    {
        $this->feature = $feature instanceof Feature
            ? $feature->value
            : $feature;

        return $this;
    }
}
