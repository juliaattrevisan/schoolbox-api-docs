<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log;

/**
 * The possible types of AI log that can be recorded corresponding to how the data should be represented
 * If the log can't be shown in the UI, it will have no type
 * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiLogFormat
 */
enum LogFormat: string
{
    /**
     * logs which can be shown using the Agent Chat logic on the frontend (conversations between the user and AI)
     */
    case AGENT_CHAT = 'agent_chat';
    /**
     * logs which correspond to Guardrail checks
     */
    case GUARDRAIL = 'guardrail';
}
