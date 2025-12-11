<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Agent\DialogAgentChat;

use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Mapper as ResponseMessageMapper;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Role;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Model as ResponseDataModel;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Model as ResponseModel;
use Schoolbox\OpenAi\AgentChat\MessageEventType;
use Schoolbox\OpenAi\EventStream\IListener;
use Schoolbox\OpenAi\EventStream\Message;

/**
 * Event stream listener for saving a persistent chat history between the AI
 * model and the student.
 */
class SaveMessageEventStreamListener implements IListener
{
    /**
     * Constructor
     *
     * @param ResponseMessageMapper $responseMessageMapper Mapper for saving messages.
     * @param ResponseModel         $responseModel         The actual response to associate messages to.
     */
    public function __construct(
        private ResponseMessageMapper $responseMessageMapper,
        private ResponseModel $responseModel,
    ) {
    }

    /**
     * Inspect `$message`, and if it's a completed response: save a new message
     * into `$this->response`.
     *
     * @param Message $message
     * @return Message `$message` is returned unchanged
     */
    public function modifyMessage(Message $message): Message
    {
        if ($message->eventType === MessageEventType::COMPLETE->value) {
            $responseMessageModel = $this->responseMessageMapper->createModel()
                ->setDialogAgentResponse(new ResponseDataModel()->setId($this->responseModel->getId()))
                ->setRole(Role::Assistant->value)
                ->setContent($message->getDataAsArray()['message'])
            ;
            // TODO set created at as exact time, not request time
            $this->responseMessageMapper->save($responseMessageModel);
        }
        return $message;
    }
}
