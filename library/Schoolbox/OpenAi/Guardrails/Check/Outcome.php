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
     * @param ICheck $check The guardrail check that produced this outcome
     * @param bool $flagged True if the guardrail flagged the input as disallowed
     * @param array $metadata Additional structured data about the check result
     */
    public function __construct(
        private ICheck $check,
        private bool $flagged,
        private array $metadata = [],
    ) {
    }

    /**
     * Retrieve the guardrail check that produced this outcome
     * @return ICheck
     */
    public function getCheck(): ICheck
    {
        return $this->check;
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

    /**
     * Format the outcome details for logs, this should match LogFormat::GUARDRAIL
     * @return array
     */
    public function asArray(): array
    {
        return [
            'guardrail' => $this->check->getName(),
            'flagged' => $this->isFlagged(),
            'metadata' => $this->getMetadata(),
        ];
    }
}
