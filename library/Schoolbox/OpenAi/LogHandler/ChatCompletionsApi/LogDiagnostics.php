<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\LogHandler\ChatCompletionsApi;

use JsonException;
use Schoolbox\OpenAi\Session\Log;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\IListener;

/**
 * Log diagnostic data for streamed messages and response bodies from
 * OpenAI's Chat Completions API:
 * https://platform.openai.com/docs/api-reference/chat-streaming
 *
 * If a streamed message or response body contains any of the following data,
 * then add it to the log:
 * * model used to handle the request
 * * token usage for the request
 * * error message
 * * status code (generated from the status of the message or response)
 *
 * This log handler should apply to all Chat Completions API requests,
 * regardless of the agent which makes them.
 */
class LogDiagnostics implements IListener
{
    /**
     * Handle the message by writing it directly to the log.
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model {
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
            $rawData = json_decode($outputMessage->data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // If it's not JSON, still save the raw data
        }

        // This chunk gets added to the log
        $log->setResponse(($log->getResponse() ?? '') . $outputMessage->__toString());

        // Otherwise, handle the data from the message as if it were a response
        $this->handleResponse($rawData, $log);
        return $log;
    }

    /**
     * Handle the response, by adding the following to the log:
     * * the model the API endpoint used
     * * the input and output tokens used
     * * an error message and response code, if this was found
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        // Find model and token usage
        $model        = $responseData['model'] ?? null;
        $inputTokens  = $responseData['usage']['prompt_tokens'] ?? null;
        $outputTokens = $responseData['usage']['completion_tokens'] ?? null;
        if ($model) {
            $log->setModel($model);
        }
        if ($inputTokens) {
            $log->setInputTokens($inputTokens);
        }
        if ($outputTokens) {
            $log->setOutputTokens($outputTokens);
        }

        // Check the finish reason for whether there was an error:
        // https://platform.openai.com/docs/api-reference/chat-streaming/streaming#chat_streaming-streaming-choices-finish_reason
        // Unfortunately none of these choices really indicates an error: the
        // closest being `length` and `content_filter`.
        foreach ($responseData['choices'] ?? [] as $choice) {
            $finishReason = $choice['finish_reason'] ?? null;
            match ($finishReason) {
                'length', 'content_filter' => $log
                    ->setReturnCode(400)
                    ->setErrorMessage("Failed to generate a full response: $finishReason."),
                'stop' => $log
                    ->setReturnCode(200)
                    ->setErrorMessage(null),
                default => $log,
            };
        }

        return $log;
    }
}
