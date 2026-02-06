<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

/**
 * Guardrail check for implementing custom content checks using an LLM.
 * Allows you to define exactly what constitutes a violation.
 * https://openai.github.io/openai-guardrails-python/ref/checks/custom_prompt_check/
 */
class UserDefinedLLM extends AbstractLLMCheck
{
    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'Custom Prompt Check';
    }

    /**
     * @inheritDoc
     */
    protected function getSystemPrompt(): string
    {
        return "# Instructions

[SYSTEM_PROMPT_DETAILS]

Flag any content that matches the criteria defined above.";
    }
}
