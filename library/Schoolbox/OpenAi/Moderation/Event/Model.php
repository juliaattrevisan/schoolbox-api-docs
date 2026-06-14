<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Event;

use Schoolbox\Mapper\Traits\Model as ModelTrait;
use Schoolbox\User;

/**
 * An event from the AI Interaction Moderation agent.
 */
class Model
{
    use ModelTrait\Id;
    use ModelTrait\CreatedAtDate;
    use ModelTrait\UpdatedAtDate;

    /**
     * The moderation session that produced the event
     *
     * @var string|null
     */
    private ?string $aiSessionId = null;

    /**
     * The AI session being moderated
     *
     * @var string|null
     */
    private ?string $targetAiSessionId = null;

    /**
     * @var string|null
     */
    private ?string $status = null;

    /**
     * @var string
     */
    private string $moderationDetails = '';

    /**
     * @var User|null
     */
    private ?User $reviewedBy = null;

    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $reviewedAt = null;

    /**
     * @var User|null
     */
    private ?User $resolvedBy = null;

    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $resolvedAt = null;

    /* Getters/Setters
     */

    /**
     * @param string $aiSessionId
     * @return self
     */
    public function setAiSessionId(string $aiSessionId): self
    {
        $this->aiSessionId = $aiSessionId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAiSessionId(): ?string
    {
        return $this->aiSessionId;
    }

    /**
     * @param string $targetAiSessionId
     * @return self
     */
    public function setTargetAiSessionId(string $targetAiSessionId): self
    {
        $this->targetAiSessionId = $targetAiSessionId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getTargetAiSessionId(): ?string
    {
        return $this->targetAiSessionId;
    }

    /**
     * Set the moderation status, validated against the known set of statuses.
     *
     * @param string|Status $status
     * @return self
     */
    public function setStatus(string|Status $status): self
    {
        $this->status = $status instanceof Status ? $status->value : $status;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param string $moderationDetails
     * @return self
     */
    public function setModerationDetails(string $moderationDetails): self
    {
        $this->moderationDetails = $moderationDetails;
        return $this;
    }

    /**
     * @return string
     */
    public function getModerationDetails(): string
    {
        return $this->moderationDetails;
    }

    /**
     * @param User|null $reviewedBy
     * @return self
     */
    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    /**
     * @param \Alaress_Date_Format|null $reviewedAt
     * @return self
     */
    public function setReviewedAt(?\Alaress_Date_Format $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format|null
     */
    public function getReviewedAt(): ?\Alaress_Date_Format
    {
        return $this->reviewedAt;
    }

    /**
     * @param User|null $resolvedBy
     * @return self
     */
    public function setResolvedBy(?User $resolvedBy): self
    {
        $this->resolvedBy = $resolvedBy;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getResolvedBy(): ?User
    {
        return $this->resolvedBy;
    }

    /**
     * @param \Alaress_Date_Format|null $resolvedAt
     * @return self
     */
    public function setResolvedAt(?\Alaress_Date_Format $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format|null
     */
    public function getResolvedAt(): ?\Alaress_Date_Format
    {
        return $this->resolvedAt;
    }
}
