<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\AgentChat;

use Schoolbox\Editor\OpenAI\Log;

/**
 * A consolidated event stream message handler for endpoints implementing Agent Chat functionality
 *
 * "Agent Chat" refers to the multi-turn conversational AI features such as the Image Generator
 * or the dialog agent learning activity. These are ultimately powered by the same underlying
 * payload structure between the Schoolbox frontend and backend, as well as similar needs
 * when parsing the messages from the AI provider's event stream.
 */
class MessageHandler
{
    /**
     * Translate a saved log's inputText and outputText back into structured data for this particular feature
     * @param Log\Model $log
     * @return array{request:string,response:array}
     */
    public static function translateLogData(Log\Model $log): array
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

        return [
            'request' => $inputText,
            'response' => $response,
        ];
    }
}
