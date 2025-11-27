<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\ImageGeneration;

use Crell\ApiProblem\ApiProblem;
use Schoolbox\Editor\OpenAI\Log;
use Schoolbox\OpenAi\AgentChat\MessageEventType;
use Schoolbox\OpenAi\EventStream\Message;
use Schoolbox\OpenAi\LogHandler\ILogHandler;
use UnexpectedValueException;

/**
 * Log listener for image generation requests.
 *
 * If a message is a finished response, then extract the output text out of it
 * and add it to the log.
 */
class LogListener implements ILogHandler
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
     * @inheritdoc
     */
    public function updateLog(Log\Model $log, Message $outputMessage, Message $rawMessage): Log\Model
    {
        /**
         * on a COMPLETE or ERROR event, there's nothing more to meaningfully continue with.
         * We should stop processing now to avoid further random messages being sent to the frontend
         *
         * This is also where the final output text can be extracted for restoring the conversation later
         */
        if (\in_array($outputMessage->eventType, self::EVENT_TYPES_FINISHED, true)) {
            /**
             * This indicates the final output for the response, it can be restored later in this exact format
             * WARNING: If you were to change this structure, old conversations may not restore correctly unless migrated
             * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/store/agentChatPayload.ts
             */
            try {
                $data = $outputMessage->getDataAsArray();
            } catch (UnexpectedValueException $e) {
                $data = null;
            }

            if (null === $data) {
                /** couldn't decode the final message data, log an error instead - It should be impossible to end up here? */
                $event = [
                    'status' => 'error',
                    'responseId' => null,
                    'message' => 'Failed to decode',
                ];
            } else if (MessageEventType::COMPLETE->value === $outputMessage->eventType) {
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
                    'responseId' => $data['responseId'] ?? null,
                    'message' => $failureMessage,
                ];
            }

            $log->setOutputText(json_encode($event, JSON_THROW_ON_ERROR));

            // TODO: This basically overrides whatever ResponseMessage::logCompletion thought the final status was, we are a lot more informed here than over there...
            // perhaps this needs to be revised to set those states properly?
        }
        return $log;
    }

    /**
     * @inheritdoc
     */
    public function handleMessage(
        Message $outputMessage,
        Message $rawMessage,
        Log\Model $log,
    ): Log\Model {
        return $this->updateLog($log, $outputMessage, $rawMessage);
    }

    /**
     * The image generation agent doesn't make non-streaming requests, so just
     * return the log as is.
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        return $log;
    }
}
