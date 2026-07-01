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
 * Describes whether the interaction shows repeated, escalating, or evasive behaviour across multiple messages.
 */
class PatternAssessment implements JsonSerializable
{
    /**
     * Constructor
     *
     * @param bool   $patternDetected true if behaviour is repeated, escalating, or shows attempts to bypass rules.
     * @param string $description     An explanation of the detected pattern. If no concerning pattern exists, state that no concerning pattern was detected.
     */
    public function __construct(
        private bool $patternDetected,
        private string $description,
    ) {
    }

    /**
     * @return bool
     */
    public function isPatternDetected(): bool
    {
        return $this->patternDetected;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return [
            'pattern_detected' => $this->patternDetected,
            'description' => $this->description,
        ];
    }

    /**
     * Build a pattern assessment from its decoded JSON representation.
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            patternDetected: (bool)($data['pattern_detected'] ?? false),
            description: (string)($data['description'] ?? ''),
        );
    }
}
