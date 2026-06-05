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
     *
     * @var string|null
     */
    private ?string $id = null;

    /**
     * The user who created the session, resolved from the `created_by` column.
     *
     * @var User|null
     */
    private ?User $user = null;

    /**
     * The AI feature that owns the session, stored as a {@see Feature} enum value.
     *
     * @var string|null
     */
    private ?string $feature = null;

    /**
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param string|null $id
     * @return $this
     */
    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * @param User|null $user
     * @return $this
     */
    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFeature(): ?string
    {
        return $this->feature;
    }

    /**
     * Accepts either a {@see Feature} enum or its string value, and stores the string value.
     *
     * @param string|Feature|null $feature
     * @return $this
     */
    public function setFeature(string|Feature|null $feature): self
    {
        $this->feature = $feature instanceof Feature
            ? $feature->value
            : $feature;

        return $this;
    }
}
