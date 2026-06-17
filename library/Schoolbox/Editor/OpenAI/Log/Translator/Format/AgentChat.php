<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Format;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\OpenAi\Session\Log\Model;

/**
 * Converts a stored AI log entry which uses the Agent Chat format into a structured array that can be used by the frontend
 * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/agentChatTypes.ts AgentChatLogEntry
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
     * @return array{type:'request',data:string,createdAt:\Alaress_Date_Format|null}
     */
    public function translateInput(Model $log): array
    {
        return [
            'type' => 'request',
            /** Input text shouldn't be NULL, but technically could be in the DB - handle just in case */
            'data' => $log->getInputText() ?? '',
            'createdAt' => $log->getCreatedAt(),
        ];
    }

    /**
     * @inheritDoc
     * @return array{type:'response',data:array,createdAt:\Alaress_Date_Format|null}
     */
    public function translateOutput(Model $log): array
    {
        $outputText = $log->getOutputText();
        $data = null;

        if (!empty($outputText)) {
            try {
                $data = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                // noop; leave null for below
            }
        }

        if (null === $data) {
            /** either failed to parse, or no output at all. Fall back to an unknown output */
            $data = [
                'status' => 'error',
                'responseId' => null,
                'message' => 'Unable to retrieve response',
            ];
        }

        return [
            'type' => 'response',
            'data' => $data,
            'createdAt' => $log->getCreatedAt(),
        ];
    }
}
