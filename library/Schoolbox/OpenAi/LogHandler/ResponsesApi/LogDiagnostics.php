<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\LogHandler\ResponsesApi;

use JsonException;
use Schoolbox\OpenAi\Session\Log;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\IListener;

/**
 * Log diagnostic data for streamed messages and full response bodies from
 * OpenAI's Response API:
 * https://platform.openai.com/docs/api-reference/responses
 *
 * If a streamed message or response body contains any of the following data,
 * then add it to the log:
 * * model used to handle the request
 * * token usage for the request
 * * error message
 * * status code (generated from the status of the message or response)
 *
 * Also, this log handler adds the full stream or response body to the log:
 * excluding any image data which may be present.
 *
 * This log handler should apply to all Response API requests, regardless of
 * the agent which makes them.
 */
class LogDiagnostics implements IListener
{
    /**
     * The following event types indicate that the response API has finished
     * processing.
     *
     * There are 3 possible events that indicate the end of a response: see
     * https://platform.openai.com/docs/api-reference/responses-streaming/response.
     */
    public const array EVENT_TYPES_FINISHED = ['response.completed', 'response.failed', 'response.incomplete', 'error'];

    /**
     * When an image is omitted from the request/response: replace the image
     * data with this in the log.
     */
    private const string IMAGE_DATA_OMITTED = 'omitted';

    /**
     * Handle the message by stripping image data when writing to the log.
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model {
        // Prepare the message for logging
        $eventType = $rawMessage->eventType;
        $rawData   = [];
        try {
            $rawData      = json_decode($rawMessage->data, true, 512, JSON_THROW_ON_ERROR);
            $filteredData = $this->filterResponseForLogging($eventType, $rawData);
            if (isset($filteredData['response'])) {
                $filteredData['response'] = $this->filterImageGenerationOutput($filteredData['response']);
            }

            // Override message data before writing it
            $rawMessage->data = json_encode($filteredData, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // If it's not JSON, still save the raw data
        }

        // This chunk gets added to the log
        $log->setResponse(($log->getResponse() ?? '') . $rawMessage->__toString());
        if (isset($rawData['response'])) {
            // Handle information about the response
            $log = $this->logUsage($rawData['response'], $log);

            // Is this the final event? If so, log its completion status.
            // There are 3 possible events that indicate the end of a response
            // (see https://platform.openai.com/docs/api-reference/responses-streaming/response)
            if (in_array($eventType, self::EVENT_TYPES_FINISHED)) {
                $log = $this->logCompletion($rawData['response'] ?? [], $log);
            }
        }
        return $log;
    }

    /**
     * @inheritdoc
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        $filteredData = $this->filterResponseForLogging(null, $responseData);
        $filteredData = $this->filterImageGenerationOutput($filteredData);
        $log = $this->logUsage($responseData, $log)->setResponse(json_encode($filteredData));
        return $this->logCompletion($responseData, $log);
    }

    /**
     * Log the usage and model from the response.
     *
     * @param array     $responseData
     * @param Log\Model $log
     * @return Log\Model
     */
    private function logUsage(array $responseData, Log\Model $log): Log\Model
    {
        // Find model and token usage
        $model        = $responseData['model'] ?? null;
        $inputTokens  = $responseData['usage']['input_tokens'] ?? null;
        $outputTokens = $responseData['usage']['output_tokens'] ?? null;
        if ($model) {
            $log->setModel($model);
        }
        if ($inputTokens) {
            $log->setInputTokens($inputTokens);
        }
        if ($outputTokens) {
            $log->setOutputTokens($outputTokens);
        }
        return $log;
    }

    /**
     * Log the completion status from the response.
     *
     * @param array     $responseData
     * @param Log\Model $log
     * @return Log\Model
     */
    private function logCompletion(array $responseData, Log\Model $log): Log\Model
    {
        /* If something went wrong on the openAI side, it'll send us an "error" key
         * https://platform.openai.com/docs/api-reference/responses/object#responses/object-error
         * If we get this, save the error message and return code.
         *
         * Note that it's not possible to raise an exception and have that
         * exception's response code be respected in the output: the response
         * headers and some of its content have already been sent.
         */
        if ($responseData["error"] ?? null) {
            $returnCode   = 500;
            $errorMessage = $responseData["error"]["message"] ?? '';

            $log->setReturnCode($returnCode)
                ->setErrorMessage($errorMessage);

            return $log;
        }

        /* Did we receive an incomplete response? i.e. max output tokens reached?
         * If so, send those details on so we have some idea of why it failed
         * https://platform.openai.com/docs/api-reference/responses/object#responses/object-status
         */
        $status = $responseData["status"] ?? null;
        if (isset($status) && $status !== "completed") {
            $returnCode        = 500;
            $incompleteMessage = $responseData["incomplete_details"]["reason"] ?? null;

            $log->setReturnCode($returnCode)
                ->setErrorMessage($incompleteMessage);

            return $log;
        }

        $log->setReturnCode($log->getReturnCode() ?? 200);
        return $log;
    }

    /**
     * Filter out unwanted fields from the response data, for storage in the
     * OpenAI log.
     *
     * @param string|null $eventType
     * @param array       $data
     * @return array
     */
    private function filterResponseForLogging(?string $eventType, array $data): array
    {
        // Omit partial and completed image data from the output
        if ($eventType === 'response.image_generation_call.partial_image' && isset($data['partial_image_b64'])) {
            $data['partial_image_b64'] = self::IMAGE_DATA_OMITTED;
        }
        if ($eventType === 'response.output_item.done' && isset($data['item']['result'])) {
            $data['item']['result'] = self::IMAGE_DATA_OMITTED;
        }

        return $data;
    }

    /**
     * Filter out image generation output from the response data, for storage in
     * the OpenAI log.
     *
     * @param array $data
     * @return array
     */
    private function filterImageGenerationOutput(array $data): array
    {
        if (isset($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $i => $respOutput) {
                if (isset($respOutput['result']) && $respOutput['type'] === 'image_generation_call') {
                    $data['output'][$i]['result'] = self::IMAGE_DATA_OMITTED;
                }
            }
        }
        return $data;
    }
}
