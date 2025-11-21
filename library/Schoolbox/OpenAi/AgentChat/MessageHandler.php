<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\AgentChat;

use Crell\ApiProblem\ApiProblem;
use Psr\Http\Client\ClientExceptionInterface;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\ResponseMessage;
use Schoolbox\OpenAi\IClient;
use Schoolbox\Editor\OpenAI\Log;
use UnexpectedValueException;

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
     * The following AgentChat\MessageEventType events indicate the end of a response
     * and when we should finalise the log and stop sending more messages to the frontend.
     */
    private const array EVENT_TYPES_FINISHED = [
        MessageEventType::COMPLETE->value,
        MessageEventType::ERROR->value,
    ];


    /**
     * Constructor
     *
     * @param IClient $client An AI provider client: needs to point to the Responses endpoint of the client
     *                        (e.g. https://platform.openai.com/docs/api-reference/responses/create).
     */
    public function __construct(private IClient $client)
    {
    }


    /**
     * Streams a payload for a feature that implements Agent Chat
     * and correctly formats the stream messages into the expected structure
     *
     * @param $payload: The request body to send.
     * @param $log: The log to write to: will be updated with the response data, so that this call may be identified in logs.
     *
     * @throws ClientExceptionInterface
     */
    public function requestStream(
        array $payload,
        Log\Model $log,
    ): iterable {
        // TODO: what happens to random errors thrown from within here? do they end up as ApiProblem's correctly?
        // TODO: Hmm.. I swear I had issues always using large chunks with it being slow for deltas
        $stream = $this->client->createResponseStream($payload, $log, 8192);

        /**
         * The response ID associated to the OpenAI request once we receive it.
         * Used by the frontend to track the response and continue the conversation
         *
         * Passed by reference to handleStreamMessage so it can be set and maintained once we receive it
         */
        $responseId = null;

        // TODO: perhaps use something similar to ILogHandler instead of doing this here
        foreach ($stream as $rawMessage) {
            $message = null;

            try {
                $message = self::handleStreamMessage($rawMessage, $responseId);
            } catch (SchoolboxException $e) {
                /** Some error occured during processing, send it onwards to the frontend as an ApiProblem */
                $problem = (new ApiProblem($e->getMessage()))->setStatus($e->getCode());

                $message = new Message(
                    json_encode($problem->asArray(), JSON_THROW_ON_ERROR),
                    MessageEventType::ERROR->value
                );
            }

            if (null === $message) {
                /** Nothing relevant to send, skip this message */
                continue;
            }

            yield $message->__toString();

            // TODO: probably log from here depending on the message type?

            /**
             * on a COMPLETE or ERROR event, there's nothing more to meaningfully continue with.
             * We should stop processing now to avoid further random messages being sent to the frontend
             *
             * This is also where the final output text can be extracted for restoring the conversation later
             */
            if (\in_array($message->eventType, self::EVENT_TYPES_FINISHED, true)) {
                /**
                 * This indicates the final output for the response, it can be restored later in this exact format
                 * WARNING: If you were to change this structure, old conversations may not restore correctly unless migrated
                 * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/store/agentChatPayload.ts
                 */
                try {
                    $data = $message->getDataAsArray();
                } catch (UnexpectedValueException $e) {
                    $data = null;
                }

                if (null === $data) {
                    /** couldn't decode the final message data, log an error instead - It should be impossible to end up here? */
                    $event = [
                        'status' => 'error',
                        'responseId' => $responseId ?? null,
                        'message' => 'Failed to decode',
                    ];
                } else if (MessageEventType::COMPLETE->value === $message->eventType) {
                    /** Successful completion */
                    $event = [
                        'status' => 'success',
                        'responseId' => $data['responseId'],
                        'image' => null,
                        'message' => $data['message'],
                    ];

                    if (is_array($data['image'])) {
                        $event['image'] = false; // false indicates that the image was generated but omitted from the log
                    }
                } else {
                    /** Something went wrong, try to derive the error details assuming it's an ApiProblem */
                    $problem = ApiProblem::fromArray($data);

                    /** @see /www/static/js/util/sbx/apiUtils.ts */
                    $failureMessage = 'An unexpected error occured';
                    if ($problem->getTitle()) {
                        $failureMessage = $problem->getTitle();
                    }
                    if ($problem->getDetail()) {
                        $failureMessage .= ': ' . $problem->getDetail();
                    }

                    $event = [
                        'status' => 'error',
                        'responseId' => $responseId ?? null,
                        'message' => $failureMessage,
                    ];
                }

                $log->setOutputText(json_encode($event, JSON_THROW_ON_ERROR));
                $this->client->saveLog($log);

                // TODO: This basically overrides whatever ResponseMessage::logCompletion thought the final status was, we are a lot more informed here than over there...
                // perhaps this needs to be revised to set those states properly?
                return;
            }
        }

        /** Reached the end of the stream without receiving a final event, something went wrong... */
        // TODO: What to do in this scenario? either just leave it and the frontend will detect an early end already, or send a specific error message?
    }


    /**
     * Handle a new message (from the createResponseStream iterable) and fully parse it
     * into a structured format for the frontend that is independent of the AI provider
     *
     * @param Message $rawMessage The raw message from the stream to be parsed
     * @param string|null &$responseId A reference to the response ID to set when we receive it so it can be reused between calls
     * @return ?Message A new Message object corresponding to one of the AgentChatMessageEventType cases
     * @throws SchoolboxException if an error occurs during processing, such as a refusal or incomplete response
     * @throws UnexpectedValueException if the message data is not valid JSON or cannot be decoded
     * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/store/agentChatPayload.ts
     */
    private static function handleStreamMessage(Message $rawMessage, ?string &$responseId = null): ?Message
    {
        // TODO: NOTE: This is just a basic clone of our current logic moved all to the backend, needs to be properly refined

        $eventType = $rawMessage->eventType;
        $data = $rawMessage->getDataAsArray();

        if (\in_array($eventType, ResponseMessage::EVENT_TYPES_FINISHED)) {
            /**
             * Event type 'error' is a special case, it likely won't be accompanied by the usual OpenAI response structure
             */
            if ('error' === $eventType) {
                $errorMessage = $data['error']['message'] ?? 'An unknown error occurred';
                throw new SchoolboxException($errorMessage, 500);
            }

            /**
             * If something went wrong on the openAI side, it'll send us an 'error' key
             * https://platform.openai.com/docs/api-reference/responses/object#responses/object-error
             * If we get this, send on the human-readable message to the user
             */
            if ($data['response']['error'] ?? null) {
                $returnCode = 500;
                $errorMessage = $data['response']['error']['message'] ?? '';
                throw new SchoolboxException($errorMessage, $returnCode);
            }

            /**
             * Did we recieve an incomplete response? i.e. max output tokens reached?
             * If so, send those details on so we have some idea of why it failed
             * https://platform.openai.com/docs/api-reference/responses/object#responses/object-status
             */
            $status = $data['response']['status'] ?? null;
            if ('response.completed' !== $eventType || 'completed' !== $status) {
                $returnCode = 500;

                $title = \sprintf('Failed to generate a response: %s', $status ?? 'unknown');

                /* there may be some extra details about why it was incomplete */
                $incompleteMessage = $data['response']['incomplete_details']['reason'] ?? null;
                if ($incompleteMessage) {
                    $title .= \sprintf(' (%s)', $incompleteMessage);
                }

                throw new SchoolboxException($title, $returnCode);
            }

            /**
             * Final event (response.completed), so let's assume we got a good response
             * It may still be a refusal, or lack what we require (such as an image for image generation)
             */

            /**
             * An array of content items generated by the model.
             * The length and order of items in the `output` array is dependent on the model's response
             * so we must filter them to find exactly what we need next
             */
            $responseOutput = $data['response']['output'] ?? [];

            /* Find the final image and message output from the response, we currently only accept one of each per request */
            $outputImageCalls = array_filter($responseOutput, fn($item) => $item['type'] === 'image_generation_call');
            $outputMessageCalls = array_filter($responseOutput, fn($item) => $item['type'] === 'message');

            $imageBase64 = array_values($outputImageCalls)[0]['result'] ?? null;
            $outputContent = array_values(array_values($outputMessageCalls)[0]['content'])[0] ?? null;

            if (null === $outputContent) {
                /**
                 * Getting here would be really weird, it would require OpenAI's structure to fail us...
                 * possibly should be handled more gracefully
                 */
                throw new UnexpectedValueException('No output content found in response');
            }

            if ('refusal' === $outputContent['type']) {
                /* The AI refused to generate what we asked of it likely due to OpenAI's moderation system, we can't do anything about that */
                throw new SchoolboxException($outputContent['refusal'], 400);
            }

            // TODO: This only applies for image generation, if this logic is split out further then it needs to be tweaked to optional for that area.
            if (null === $imageBase64) {
                /**
                 * No output image? probably a refusal in the form of text
                 * This could occur on the Image Generator if the system prompt specifically disallows
                 * certain subjects and tells the AI to not generate an image and only respond with text.
                 *
                 * It will look like a completely valid response, just without an image.
                 */

                // TODO: For image generation requests, this is considered a failure - anywhere else it may be valid, so we need to handle that correctly (i.e. just continue on)

                throw new SchoolboxException($outputContent['text'], 400);
            }

            /* The frontend expects a type of 'complete' for the final message, it will then terminate the stream */
            return new Message(
                json_encode([
                    'responseId' => $responseId ?? $data['response']['id'] ?? null,
                    'image' => self::translateImageData($imageBase64),
                    'message' => $outputContent['text'] ?? '',
                ], JSON_THROW_ON_ERROR),
                MessageEventType::COMPLETE->value
            );
        }

        /** intermediate event, convert to our standardised format if it's something we care about */

        if ('response.created' === $data['type']) {
            /** Usually the first message we will receive from the AI, take note of the response ID so we can re-use it later */
            $responseId = $data['response']['id'] ?? null;

            return new Message(
                json_encode(['responseId' => $responseId], JSON_THROW_ON_ERROR),
                MessageEventType::CREATED->value
            );
        }

        if ('response.image_generation_call.partial_image' === $data['type']) {
            /** Partial image was generated, this helps the user see progress and not just a loading spinner for ages */
            $imageBase64 = $data['partial_image_b64'] ?? null;

            if (null === $imageBase64) {
                /** Weird, no image data? ignore it I guess */
                return null;
            }

            return new Message(
                json_encode([
                    'responseId' => $responseId,
                    'image' => self::translateImageData($imageBase64),
                ], JSON_THROW_ON_ERROR),
                MessageEventType::PARTIAL_IMAGE->value
            );
        }

        if ('response.output_text.delta' === $data['type']) {
            /** A delta of text output from the AI, will be appended to the current message to show the user that something is happening */
            $textDelta = $data['delta'] ?? null;

            if (null === $textDelta) {
                /** Weird, no text delta ? ignore it I guess */
                return null;
            }

            return new Message(
                json_encode([
                    'responseId' => $responseId,
                    'delta' => $textDelta,
                ], JSON_THROW_ON_ERROR),
                MessageEventType::DELTA->value
            );
        }

        /** any other event type doesn't matter to us, ignore it */
        return null;
    }


    /**
     * Translate a base64 image into an array expected by the frontend for agent chat messages
     * @param string $base64Image
     * @return array
     * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/agentChatTypes.ts AgentChatImage
     */
    private static function translateImageData(string $base64Image): array
    {
        /* currently we always expect a PNG back from the AI, so assume it is */
        // TODO: this should actually be linked to ImageGeneration::IMAGE_OUTPUT_FORMAT in some way I guess
        return [
            'b64' => $base64Image,
            'name' => 'image.png',
            'mime' => 'image/png',
        ];
    }


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
