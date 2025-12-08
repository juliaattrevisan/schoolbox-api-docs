<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response;

use Schoolbox\Learning\Assessment\AbstractResponse;
use Schoolbox\Mapper\Traits\Model\CreatedBy;
use Schoolbox\Mapper\Traits\Model\DeletedBy;
use Schoolbox\Mapper\Traits\Model\UpdatedBy;

class Model extends AbstractResponse
{
    use CreatedBy;
    use UpdatedBy;
    use DeletedBy;


    /**
     * @var \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    private ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent = null;


    /**
     * @var bool|null
     */
    private ?bool $authoritative = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $startedAt = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $submittedAt = null;


    /**
     * @var string|null
     */
    private ?string $sessionId = null;


    /**
     * @var \Alaress_Date_Format|null
     */
    private ?\Alaress_Date_Format $assistanceRequestedAt = null;


    /**
     * Interrogate this instance for the current state that it's in
     *
     * @return string
     */
    public function getCurrentStatus(): string
    {
        /* Submitted: disregard all other statuses
         */
        if (null !== $this->getSubmittedAt()) {
            return Status::SUBMITTED->value;
        }

        // TODO: deal with duration eventually. still allow SOBO from staff

        /* A session identifier may or may not exist, not yet submitted: participant is still doing the stuff
         * and maybe needs help. Perhaps they need help before they even get to the point of having a session id.
         *
         * If assistance was requested and the response is still in progress, send a special status
         */
        if (null !== $this->getAssistanceRequestedAt()) {
            return Status::REQUIRES_ASSISTANCE->value;
        }

        /* No session identifier yet; a draft exists but the participant hasn't sent their first message to ai services
         */
        if (null === $this->getSessionId()) {
            return Status::INCOMPLETE->value;
        }

        /* Otherwise we're just doing the stuff
         */
        return Status::IN_PROGRESS->value;
    }


    /**
     * Returns a human-readable equivalent of the current status
     *
     * @return string
     */
    public function getCurrentStatusHR(): string
    {
        return Status::tryFrom($this->getCurrentStatus())->translate();
    }


    /* Getters/Setters
     */

    /**
     * @return \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null
     */
    public function getDialogAgent(): ?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model
    {
        return $this->dialogAgent;
    }


    /**
     * @param \Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model|null $dialogAgent
     * @return $this
     */
    public function setDialogAgent(?\Schoolbox\Learning\Assessment\DataModel\DialogAgent\Model $dialogAgent): self
    {
        $this->dialogAgent = $dialogAgent;
        return $this;
    }


    /**
     * @return bool|null
     */
    public function getAuthoritative(): ?bool
    {
        return $this->authoritative;
    }


    /**
     * @param bool|null $authoritative
     * @return $this
     */
    public function setAuthoritative(?bool $authoritative): self
    {
        $this->authoritative = $authoritative;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getStartedAt(): ?\Alaress_Date_Format
    {
        return $this->startedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $startedAt
     * @return $this
     */
    public function setStartedAt(?\Alaress_Date_Format $startedAt): self
    {
        $this->startedAt = $startedAt;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getSubmittedAt(): ?\Alaress_Date_Format
    {
        return $this->submittedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $submittedAt
     * @return $this
     */
    public function setSubmittedAt(?\Alaress_Date_Format $submittedAt): self
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }


    /**
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }


    /**
     * @param string|null $sessionId
     * @return $this
     */
    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }


    /**
     * @return \Alaress_Date_Format|null
     */
    public function getAssistanceRequestedAt(): ?\Alaress_Date_Format
    {
        return $this->assistanceRequestedAt;
    }


    /**
     * @param \Alaress_Date_Format|null $assistanceRequestedAt
     * @return $this
     */
    public function setAssistanceRequestedAt(?\Alaress_Date_Format $assistanceRequestedAt): self
    {
        $this->assistanceRequestedAt = $assistanceRequestedAt;
        return $this;
    }

    /**
     * Calculate how long this response took to complete; returns number of seconds elapsed
     *
     * @return int
     */
    public function getTimeTaken(): int
    {
        $startedAt = $this->getStartedAt() ?? $this->getCreatedAt();
        $endedAt = $this->getSubmittedAt() ?? \Alaress_Date_Format::now();

        return $endedAt->diffInSeconds($startedAt);
    }
}
