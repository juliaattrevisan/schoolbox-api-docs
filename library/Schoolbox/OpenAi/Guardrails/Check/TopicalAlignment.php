<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Guardrails\Check;

/**
 * Guardrail check for ensuring content stays within a specified business scope or topic domain.
 * It uses an LLM to analyze text against a defined context to detect off-topic or irrelevant content.
 * https://openai.github.io/openai-guardrails-python/ref/checks/off_topic_prompts/
 *
 * The provided $client must point to the Responses endpoint.
 * The provided $config should be an array with {
 *      model: string,                 // LLM model to use for checking the text
 *      system_prompt_details: string, // Description of the allowed business scope or on-topic context
 *      confidence_threshold: ?float,  // (Optional) Minimum confidence threshold to trigger the guardrail (0.0 to 1.0) (default: 0.7)
 *      previous_response_id: ?string, // (Optional) Previous response ID for context continuity
 * }
 */
class TopicalAlignment extends AbstractLLMCheck
{
    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'Off Topic Prompts';
    }

    /**
     * @inheritDoc
     */
    public static function getDescription(): string
    {
        return 'Checks that the content stays within the defined business scope';
    }

    /**
     * @inheritDoc
     */
    protected function getSystemPrompt(): string
    {
        return "You are a content analysis system that determines if text stays on topic.

BUSINESS SCOPE: [SYSTEM_PROMPT_DETAILS]

Determine if the text stays within the defined business scope. Flag any content
that strays from the allowed topics.";
    }
}
