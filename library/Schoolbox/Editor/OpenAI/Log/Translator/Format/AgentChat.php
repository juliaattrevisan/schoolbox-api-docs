<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Format;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\Editor\OpenAI\Log\Model;

/**
 * Converts a stored AI log entry which uses the Agent Chat format into a structured array that can be used by the frontend
 * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/agentChatTypes.ts AiLogAgentChatData
 */
class AgentChat implements IStrategy
{
    /**
     * @inheritDoc
     */
    public static function getLogFormat(): LogFormat
    {
        return LogFormat::AGENT_CHAT;
    }

    /**
     * @inheritDoc
     * @return (array{type:'request',data:string}|array{type:'response',data:array})[]
     */
    public function translate(Model $log): array
    {
        $inputText = $log->getInputText();
        $outputText = $log->getOutputText();

        $response = null;
        if (!empty($outputText)) {
            try {
                $response = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // noop; leave null for below
            }
        }

        if (null === $response) {
            /** either failed to parse, or no output at all. Fall back to an unknown output */
            $response = [
                'status' => 'error',
                'responseId' => null,
                'message' => 'Unable to retrieve response',
            ];
        }

        /** this could be extended to allow logs of more than 1 request/response per log entry, or even other new types */
        return [
            ['type' => 'request', 'data' => $inputText],
            ['type' => 'response', 'data' => $response],
        ];
    }
}
