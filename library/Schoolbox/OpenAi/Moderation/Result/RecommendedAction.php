<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Result;

use JsonSerializable;

/**
 * Suggested level of response for the school based on the interaction.
 */
class RecommendedAction implements JsonSerializable
{
    /**
     * Constructor
     *
     * @param ActionLevel $actionLevel The suggested school response level.
     * @param string      $why         Explain why this action level is recommended.
     */
    public function __construct(
        private ActionLevel $actionLevel,
        private string $why,
    ) {
    }

    /**
     * @return ActionLevel
     */
    public function getActionLevel(): ActionLevel
    {
        return $this->actionLevel;
    }

    /**
     * @return string
     */
    public function getWhy(): string
    {
        return $this->why;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'action_level' => $this->actionLevel->value,
            'why' => $this->why,
        ];
    }

    /**
     * Build a recommended action from an array.
     *
     * @param array $data
     * @return self
     * @throws \ValueError for a mismatch in expected data types (such as an invalid enum value)
     */
    public static function fromArray(array $data): self
    {
        return new self(
            actionLevel: ActionLevel::from((string)($data['action_level'] ?? '')),
            why: (string)($data['why'] ?? ''),
        );
    }
}
