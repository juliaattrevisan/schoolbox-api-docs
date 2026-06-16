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
use Schoolbox\OpenAi\Feature;
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
     * Log format derived from the session's first log, for the admin list only.
     * Not a column; populated by {@see Mapper::getFilteredSessions()}.
     *
     * @var string|null
     */
    private ?string $derivedLogFormat = null;

    /**
     * Return code derived from the session's first log, for the admin list only.
     * Not a column; populated by {@see Mapper::getFilteredSessions()}.
     *
     * @var int|null
     */
    private ?int $derivedReturnCode = null;

    /**
     * Whether any log in this session has a non-200 return code, for the admin list only.
     * Not a column; populated by {@see Mapper::getFilteredSessions()}.
     *
     * @var bool
     */
    private bool $derivedHasError = false;

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
     * @throws \ValueError If $feature is not a valid Feature value.
     *
     * @param string|Feature $feature
     * @return $this
     */
    public function setFeature(string|Feature $feature): self
    {
        $this->feature = $feature instanceof Feature
            ? $feature->value
            : Feature::from($feature)->value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDerivedLogFormat(): ?string
    {
        return $this->derivedLogFormat;
    }

    /**
     * @param string|null $derivedLogFormat
     * @return $this
     */
    public function setDerivedLogFormat(?string $derivedLogFormat): self
    {
        $this->derivedLogFormat = $derivedLogFormat;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getDerivedReturnCode(): ?int
    {
        return $this->derivedReturnCode;
    }

    /**
     * @param int|null $derivedReturnCode
     * @return $this
     */
    public function setDerivedReturnCode(?int $derivedReturnCode): self
    {
        $this->derivedReturnCode = $derivedReturnCode;
        return $this;
    }

    /**
     * @return bool
     */
    public function getDerivedHasError(): bool
    {
        return $this->derivedHasError;
    }

    /**
     * @param bool $derivedHasError
     * @return $this
     */
    public function setDerivedHasError(bool $derivedHasError): self
    {
        $this->derivedHasError = $derivedHasError;
        return $this;
    }
}
