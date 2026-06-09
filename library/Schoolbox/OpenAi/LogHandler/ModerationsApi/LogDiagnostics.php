<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\LogHandler\ModerationsApi;

use Schoolbox\OpenAi\Session\Log;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\IListener;

/**
 * Log diagnostic data for response bodies from OpenAI's Moderations API:
 * https://platform.openai.com/docs/api-reference/moderations
 *
 * Streamed messages are not supported by the Moderations API
 *
 * If a streamed message or response body contains any of the following data,
 * then add it to the log:
 * * model used to handle the request
 * * token usage for the request
 * * error message
 * * status code (generated from the status of the message or response)
 *
 * This log handler should apply to all Moderations API requests,
 * regardless of the agent which makes them.
 */
class LogDiagnostics implements IListener
{
    /**
     * @inheritDoc
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model {
        // Moderations API doesn't support streaming
        return $this->handleResponse($outputMessage->getDataAsArray(), $log);
    }

    /**
     * Handle the response, by adding the following to the log:
     * * the raw response body
     * * the model the API endpoint used
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        $log->setResponse(json_encode($responseData));

        // Find model
        $model = $responseData['model'] ?? null;
        if ($model) {
            $log->setModel($model);
        }

        // Set return code based on presence of results
        $results = $responseData['results'] ?? null;
        if (!isset($results) || !\is_array($results) || 0 === \count($results)) {
            $log->setReturnCode(500);
        } else {
            $log->setReturnCode(200);
        }

        return $log;
    }
}
