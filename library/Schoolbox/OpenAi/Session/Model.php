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
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
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
     * Log format derived from the session's first log.
     * This is a summary hint for the admin list, not a guarantee that every log in the session has this format.
     * This is used to determine whether any log in the session is renderable (since rendering requires a known log
     * format - see aul-agent-chat.vue).
     * Populated by:
     *  - @see Mapper::getFilteredSessions()
     *  - @see SessionSummariser::hydrateSessionsWithSummaryInfo()
     *
     * @var string|null
     */
    private ?string $derivedFirstViewableLogFormat = null;

    /**
     * Whether any log in this session has an error condition.
     * Populated by:
     *  - @see Mapper::getFilteredSessions()
     *  - @see SessionSummariser::hydrateSessionsWithSummaryInfo()
     *
     * @var bool
     */
    private bool $derivedHasError = false;

    /**
     * The highest-priority moderation event status for this session.
     * Populated by:
     *  - @see Mapper::getFilteredSessions()
     *  - @see SessionSummariser::hydrateSessionsWithSummaryInfo()
     *
     * @var string|null
     */
    private ?string $derivedModerationStatus = null;

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
    public function getDerivedFirstViewableLogFormat(): ?string
    {
        return $this->derivedFirstViewableLogFormat;
    }

    /**
     * @param string|null $derivedFirstViewableLogFormat
     * @return $this
     */
    public function setDerivedFirstViewableLogFormat(?string $derivedFirstViewableLogFormat): self
    {
        $this->derivedFirstViewableLogFormat = $derivedFirstViewableLogFormat;
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

    /**
     * @return string|null
     */
    public function getDerivedModerationStatus(): ?string
    {
        return $this->derivedModerationStatus;
    }

    /**
     * @param string|null $derivedModerationStatus
     * @return $this
     */
    public function setDerivedModerationStatus(string|null $derivedModerationStatus): self
    {
        $this->derivedModerationStatus = (
            null === $derivedModerationStatus
            ? null
            : ModerationEventStatus::from($derivedModerationStatus)->value
        );

        return $this;
    }
}
