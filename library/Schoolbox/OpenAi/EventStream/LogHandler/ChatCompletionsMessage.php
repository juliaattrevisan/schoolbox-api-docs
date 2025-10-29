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
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Handle messages from OpenAI's Response API:
 * https://platform.openai.com/docs/api-reference/responses-streaming
 */
class ChatCompletionsMessage implements ILogHandler
{
    /**
     * When an image is omitted from the request/response: replace the image
     * data with this in the log.
     */
    private const string IMAGE_DATA_OMITTED = 'omitted';


    public function handle(Message $message, Log\Model $log): Log\Model
    {
        // Prepare the message for logging
        $eventType = $message->eventType;
        $data      = [];
        try {
            // Need to handle different types of response:
            // * chat completions returns messages with no event type
            // * responses returns messages with event types, which should be
            //   handled uniquely

            // Omit partial and completed image data from the output
            $item   = json_decode($message->data, true, 512, JSON_THROW_ON_ERROR);
            $data[] = $item;
            if ($eventType === 'response.image_generation_call.partial_image' && isset($item['partial_image_b64'])) {
                $item['partial_image_b64'] = self::IMAGE_DATA_OMITTED;
            }
            if ($eventType === 'response.output_item.done' && isset($item['item']['result'])) {
                $item['item']['result'] = self::IMAGE_DATA_OMITTED;
            }

            // apparently there is a final final image, lets remove that also
            if ($eventType === 'response.completed' && isset($item['response']['output']) && is_array($item['response']['output'])) {
                // $item['response']['output'] is apparently an array, and we need to omit the b64 from each $item['response']['output'][]['result']
                foreach ($item['response']['output'] as $i => $respOutput) {
                    if (isset($respOutput['result'])) {
                        $item['response']['output'][$i]['result'] = self::IMAGE_DATA_OMITTED;
                    }
                }
            }

            // Override message data before writing it
            $message->data = json_encode($item, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            // If it's not JSON, still save the raw data
        }

        // This chunk gets added to the log
        $this->appendMessageToLog($message, $data, $log);
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
     * @param array     $data    The unfiltered parsed data from the message: the model and token usage will be
     *                           extracted from here.
     * @param Log\Model $log     The log to write to.
     * @return void
     */
    private function appendMessageToLog(Message $message, array $data, Log\Model $log): void
    {
        $log->setResponse(($log->getResponse() ?? '') . $message->__toString());
        foreach ($data as $item) {
            // Find model and token usage
            $model        = $item['response']['model'] ?? null;
            $inputTokens  = $item['response']['usage']['input_tokens'] ?? null;
            $outputTokens = $item['response']['usage']['output_tokens'] ?? null;
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
            // There are 3 possible events that indicate the end of a response
            // (see https://platform.openai.com/docs/api-reference/responses-streaming/response)
            if (in_array($message->eventType, ['response.completed', 'response.failed', 'response.incomplete'])) {
                /* If something went wrong on the openAI side, it'll send us an "error" key
                 * https://platform.openai.com/docs/api-reference/responses/object#responses/object-error
                 * If we get this, send on the human-readable message to the user
                 */
                if ($item["error"] ?? null) {
                    $returnCode = 500;
                    $errorMessage = $item["error"]["message"] ?? '';

                    $log->setReturnCode($returnCode)
                        ->setErrorMessage($errorMessage);

                    throw new SchoolboxException($errorMessage, $returnCode);
                }

                /* Did we receive an incomplete response? i.e. max output tokens reached?
                 * If so, send those details on so we have some idea of why it failed
                 * https://platform.openai.com/docs/api-reference/responses/object#responses/object-status
                 */
                $status = $item["status"] ?? null;
                if (isset($status) && $status !== "completed") {
                    $returnCode = 500;
                    $title      = sprintf("Failed to generate a response: %s", $status);

                    /* there may be some extra details about why it was incomplete */
                    $incompleteMessage = $item["incomplete_details"]["reason"] ?? null;
                    if ($incompleteMessage) {
                        $title .= sprintf(" (%s)", $incompleteMessage);
                    }

                    $log->setReturnCode($returnCode)
                        ->setErrorMessage($incompleteMessage);

                    throw new SchoolboxException($title, $returnCode);
                }

                $log->setReturnCode(200);
            }
        }
    }
}
