<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

class Candidate
{
    /**
     * @param string                   $sessionId          The AI session requiring moderation.
     * @param \Alaress_Date_Format     $lastMessageSentAt  The latest log timestamp for the session.
     * @param \Alaress_Date_Format|null $lastModeratedAt   The latest moderation event timestamp, when one exists.
     * @param ?string                  $feature            The feature of the session, which is used to determine if it supports moderation.
     */
    public function __construct(
        private readonly string $sessionId,
        private readonly \Alaress_Date_Format $lastMessageSentAt,
        private readonly ?\Alaress_Date_Format $lastModeratedAt,
        private readonly ?string $feature,
    ) {
    }

    /**
     * @return string
     */
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getLastMessageSentAt(): \Alaress_Date_Format
    {
        return $this->lastMessageSentAt;
    }

    /**
     * @return \Alaress_Date_Format|null
     */
    public function getLastModeratedAt(): ?\Alaress_Date_Format
    {
        return $this->lastModeratedAt;
    }

    /**
     * @return ?string
     */
    public function getFeature(): ?string
    {
        return $this->feature;
    }
}
