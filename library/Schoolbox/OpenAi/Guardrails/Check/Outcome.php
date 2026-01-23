<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

/**
 * Encapsulates the outcome of a guardrail check,
 * including whether a flag was raised and any supplementary metadata.
 */
class Outcome
{
    /**
     * @param bool $flagged True if the guardrail flagged the input as disallowed
     * @param array $metadata Additional structured data about the check result
     */
    public function __construct(
        private bool $flagged,
        private array $metadata = [],
    ) {
    }

    /**
     * If the guardrail flagged the input as disallowed
     * @return bool
     */
    public function isFlagged(): bool
    {
        return $this->flagged;
    }

    /**
     * Additional structured data about the check result
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
