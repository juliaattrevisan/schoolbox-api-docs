<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\ModerationQueue;

use Schoolbox\OpenAi\Moderation\Event\Model as ModerationEventModel;

class ModerationResult
{
    private function __construct(
        private readonly bool $moderated,
        private readonly ?string $reason,
        private readonly ?ModerationEventModel $event,
    ) {
    }

    /**
     * @param ModerationEventModel $event The persisted moderation event.
     * @return self
     */
    public static function moderated(ModerationEventModel $event): self
    {
        return new self(true, null, $event);
    }

    /**
     * @param string $reason A stable no-op reason for caller logging and counting.
     * @return self
     */
    public static function noOp(string $reason): self
    {
        return new self(false, $reason, null);
    }

    /**
     * @return bool
     */
    public function isModerated(): bool
    {
        return $this->moderated;
    }

    /**
     * @return bool
     */
    public function isNoOp(): bool
    {
        return !$this->moderated;
    }

    /**
     * @return string|null
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @return ModerationEventModel|null
     */
    public function getEvent(): ?ModerationEventModel
    {
        return $this->event;
    }
}
