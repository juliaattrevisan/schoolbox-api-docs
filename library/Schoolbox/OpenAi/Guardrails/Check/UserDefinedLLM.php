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
 *
 * The provided $client must point to the Responses endpoint.
 * The provided $config should be an array with {
 *      model: string,                 // LLM model to use for checking the text
 *      system_prompt_details: string, // Free-form instructions describing content moderation requirements
 *      confidence_threshold: ?float,  // (Optional) Minimum confidence threshold to trigger the guardrail (0.0 to 1.0) (default: 0.7)
 *      previous_response_id: ?string, // (Optional) Previous response ID for context continuity
 * }
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
    public static function getDescription(): string
    {
        return 'User-defined LLM guardrail for custom content moderation';
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
