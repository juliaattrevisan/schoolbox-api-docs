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
    protected function getSystemPrompt(): string
    {
        return "You are a content analysis system that determines if text stays on topic.

BUSINESS SCOPE: [SYSTEM_PROMPT_DETAILS]

Determine if the text stays within the defined business scope. Flag any content
that strays from the allowed topics.";
    }
}
