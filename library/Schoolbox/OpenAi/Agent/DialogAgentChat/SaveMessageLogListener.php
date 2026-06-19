<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\DialogAgentChat;

use Alaress_Date_Format;
use Schoolbox\OpenAi\Session\Log;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\OpenAi\AgentChat\MessageEventType;
use Schoolbox\OpenAi\LogHandler\IListener;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Log listener for saving a persistent chat history between the AI model and
 * the student.
 */
class SaveMessageLogListener implements IListener
{
    /**
     * The following event types are handled by this listener:
     * * start of a response
     * * end of a response
     */
    private const array EVENT_TYPES_HANDLED = [
        MessageEventType::CREATED->value,
        MessageEventType::COMPLETE->value,
        MessageEventType::ERROR->value,
    ];

    /**
     * Constructor
     *
     * @param Response\Message\Mapper $responseMessageMapper Mapper for saving messages.
     * @param ResponseModel           $responseModel         The actual response to associate messages to.
     */
    public function __construct(
        private Response\Message\Mapper $responseMessageMapper,
        private ResponseModel $responseModel,
    ) {
    }

    /**
     * Inspect `$outputMessage`, and if it's a completed response: save a new message
     * into `$this->response`.
     *
     * @param Message   $outputMessage
     * @param Message   $rawMessage
     * @param Log\Model $log
     * @return Log\Model `$log` is returned unchanged
     * @throws MapperException if the message couldn't be saved
     */
    public function handleMessage(Message $outputMessage, Message $rawMessage, Log\Model $log): Log\Model
    {
        // Don't bother logging if a partial response: only if the response was
        // that something was created, completed or an error.
        if (!in_array($outputMessage->eventType, self::EVENT_TYPES_HANDLED, true)) {
            return $log;
        }
        $responseStatus = Response\Message\Status::fromMessageEventType(
            MessageEventType::tryFrom($outputMessage->eventType) ?? MessageEventType::CREATED,
        );

        // If there is no ai_response_id, don't log anything: there's no way to
        // associate this to any previous log.
        $messageData  = $outputMessage->getDataAsArray();
        $aiResponseId = $messageData['responseId'] ?? null;
        if (!isset($aiResponseId)) {
            return $log;
        }

        // Try to find the message with this ai_response_id, and update it if it
        // exists: otherwise create a new one.
        $responseMessageModel = $this->responseMessageMapper
            ->findByColumn('ai_response_id', $aiResponseId)
            ->first();
        $responseMessageModel ??= $this->responseMessageMapper->createModel();

        // Set whatever fields on the response message, and save.
        $responseMessageModel = $responseMessageModel
            ->setDialogAgentResponse(new Response\Model()->setId($this->responseModel->getId()))
            ->setAiResponseId($aiResponseId)
            ->setRole(Response\Message\Role::Assistant->value)
            ->setStatus($responseStatus->value)
            ->setContent($messageData['message'] ?? '')
            // Set the creation time to the current time,
            // instead of the request time:
            // this more accurately reflects when the response was received.
            ->setCreatedAt(Alaress_Date_Format::newFromTimestamp(time()))
        ;
        $this->responseMessageMapper->save($responseMessageModel);
        return $log;
    }

    /**
     * Does nothing, as this doesn't need to work on non-streaming responses
     * yet.
     *
     * @param array     $responseData
     * @param Log\Model $log
     * @return Log\Model `$log` is returned unchanged
     */
    public function handleResponse(array $responseData, Log\Model $log): Log\Model
    {
        return $log;
    }
}
