<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\ImageGeneration;

use Crell\ApiProblem\ApiProblem;
use JsonException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\OpenAi\AgentChat\MessageEventType;
use Schoolbox\OpenAi\EventStream\IListener;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\ResponseMessage;
use UnexpectedValueException;

/**
 * Event listener for image generation requests.
 *
 * Mutate the received event into something which the frontend is able to
 * consume.
 */
class EventListener implements IListener
{
    /**
     * The response ID which OpenAI sent for this request.
     *
     * Will be included in the data for any subsequent events after this is
     * received.
     */
    private ?string $responseId = null;

    /**
     * @inheritdoc
     */
    public function modifyMessage(Message $message): ?Message
    {
        try {
            return $this->handleStreamMessage($message);
        } catch (SchoolboxException $e) {
            /** Some error occurred during processing, send it onwards to the frontend as an ApiProblem */
            $problem = new ApiProblem($e->getMessage())->setStatus($e->getCode());
        } catch (JsonException $e) {
            // JSON output exception
            $problem = new ApiProblem($e->getMessage());
        }

        // This should always encode,
        // but in case it fails just show the message
        try {
            $messageData = json_encode($problem->asArray(), JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $messageData = $e->getMessage();
        }
        return new Message($messageData, MessageEventType::ERROR->value);
    }

    /**
     * Handle a new message (from the createResponseStream iterable) and fully parse it
     * into a structured format for the frontend that is independent of the AI provider
     *
     * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/store/agentChatPayload.ts
     *
     * @param Message $rawMessage The raw message from the stream to be parsed
     * @return ?Message A new Message object corresponding to one of the AgentChatMessageEventType cases
     * @throws SchoolboxException       if an error occurs during processing, such as a refusal or incomplete response
     * @throws UnexpectedValueException if the message data is not valid JSON or cannot be decoded
     * @throws JsonException            if for some reason message data failed to encode
     */
    private function handleStreamMessage(Message $rawMessage): ?Message
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

            if (null === $imageBase64/* && $this->requireFinalImage*/) {
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
                json_encode(
                    [
                        'responseId' => $this->responseId ?? $data['response']['id'] ?? null,
                        'image'      => $imageBase64 ? self::translateImageData($imageBase64) : null,
                        'message'    => $outputContent['text'] ?? '',
                    ],
                    JSON_THROW_ON_ERROR,
                ),
                MessageEventType::COMPLETE->value
            );
        }

        /** intermediate event, convert to our standardised format if it's something we care about */

        if ('response.created' === $data['type']) {
            /** Usually the first message we will receive from the AI, take note of the response ID so we can re-use it later */
            $this->responseId = $data['response']['id'] ?? null;

            return new Message(
                json_encode(['responseId' => $this->responseId], JSON_THROW_ON_ERROR),
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
                json_encode(
                    [
                        'responseId' => $this->responseId,
                        'image'      => self::translateImageData($imageBase64),
                    ],
                    JSON_THROW_ON_ERROR
                ),
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
                json_encode(
                    [
                        'responseId' => $this->responseId,
                        'delta'      => $textDelta,
                    ],
                    JSON_THROW_ON_ERROR
                ),
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
}
