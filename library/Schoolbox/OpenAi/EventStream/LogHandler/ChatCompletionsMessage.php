<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\EventStream\LogHandler;

use JsonException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Editor\OpenAI\Log;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Handle messages from OpenAI's Chat Completions API:
 * https://platform.openai.com/docs/api-reference/chat/streaming
 */
class ChatCompletionsMessage implements ILogHandler
{
    public function handle(Message $message, Log\Model $log): Log\Model
    {
        // Chat completions messages don't have an event type:
        // just write the message data directly to the log.

        // Prepare the message for logging
        $rawData = [];
        try {
            // Need to handle different types of response:
            // * chat completions returns messages with no event type
            // * responses returns messages with event types, which should be
            //   handled uniquely

            // Omit partial and completed image data from the output
            $rawData = json_decode($message->data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // If it's not JSON, still save the raw data
        }

        // This chunk gets added to the log
        $this->appendMessageToLog($message, $rawData, $log);
        return $log;
    }

    /**
     * Append the message to the log, including:
     * * the message itself (with redundant field filtered)
     * * the model the API endpoint used
     * * the input and output tokens used
     *
     * This also saves the log in its probably incomplete state.
     *
     * @param Message   $message The message, with filtered fields, to be written direct to the log.
     * @param array     $rawData The unfiltered parsed data from the message: the model and token usage will be
     *                           extracted from here.
     * @param Log\Model $log     The log to write to.
     * @return void
     */
    private function appendMessageToLog(Message $message, array $rawData, Log\Model $log): void
    {
        $log->setResponse(($log->getResponse() ?? '') . $message->__toString());

        // Find model and token usage
        $model        = $rawData['model'] ?? null;
        $inputTokens  = $rawData['usage']['prompt_tokens'] ?? null;
        $outputTokens = $rawData['usage']['completion_tokens'] ?? null;
        if ($model) {
            $log->setModel($model);
        }
        if ($inputTokens) {
            $log->setInputTokens($inputTokens);
        }
        if ($outputTokens) {
            $log->setOutputTokens($outputTokens);
        }

        // Is this the final event? If so, check for errors.
        // TODO check if it actually is the final event

        $log->setReturnCode(200);
    }
}
