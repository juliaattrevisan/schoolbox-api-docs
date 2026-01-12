<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\DialogAgentChat;

use Alaress_Date_Format;
use Schoolbox\Editor\OpenAI\Log;
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
        $responseMessageModel = null;
        $aiResponseId         = $outputMessage->getDataAsArray()['responseId'] ?? null;
        if (isset($aiResponseId)) {
            // Find the response: if none found, use a new one
            // TODO index ai_response_id?
            /** @var Response\Message\Model $responseMessageModel */
            $responseMessageModel = $this->responseMessageMapper
                ->findByColumn('ai_response_id', $aiResponseId)
                ->first();
        }
        $responseMessageModel ??= $this->responseMessageMapper->createModel();

        $responseStatus       = MessageEventType::tryFrom($outputMessage->eventType) ?? MessageEventType::CREATED;
        $responseMessageModel = $responseMessageModel
            ->setDialogAgentResponse(new Response\Model()->setId($this->responseModel->getId()))
            ->setAiResponseId($aiResponseId)
            ->setRole(Response\Message\Role::Assistant->value)
            ->setStatus(Response\Message\Status::fromMessageEventType($responseStatus)->value)
            ->setContent($outputMessage->getDataAsArray()['message'] ?? '')
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
