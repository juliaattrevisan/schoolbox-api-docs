<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Translator;

use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Model;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Role;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message\Status;
use Schoolbox\OpenAi\Agent\DialogAgentChat;

class ModelToArray
{
    /**
     * Translates a dialog agent response message model into an array
     * This must follow the output format of the AgentChat frontend component
     *
     * @param Model $model
     * @return array
     * @see /library/Schoolbox/Editor/OpenAI/templates/agentChat/agentChatTypes.ts AgentChatLogEntry
     */
    public function __invoke(Model $model): array
    {
        if ($model->getStatus() === Status::GuardrailFlagged->value) {
            /** A guardrail failed */

            return [
                'type' => 'alert',
                'data' => [
                    'side' => 'response',
                    'severity' => 'warning',
                    // TODO: Perhaps show something more relevant for instructors?
                    'message' => DialogAgentChat::GUARDRAIL_FAIL_LEARNER_MESSAGE,
                ],
                'createdAt' => $model->getCreatedAt(),
            ];
        }

        /** Input/output text shouldn't be NULL, but technically could be in the DB - handle just in case */
        $message = $model->getContent() ?? '';

        if ($model->getRole() === Role::User->value) {
            $data = $message;
        } elseif ($model->getStatus() === Status::Ok->value) {
            /** A successful response message */
            $data = [
                'status' => 'success',
                'responseId' => $model->getAiResponseId(),
                'image' => null, // unsupported by dialog agent chat
                'message' => $message,
            ];
        } else {
            /** An error response message, or unknown format/error */
            $data = [
                'status' => 'error',
                'responseId' => $model->getAiResponseId(),
                'message' => $message,
            ];
        }

        return [
            'type' => $model->getRole() === Role::User->value ? 'request' : 'response',
            'data' => $data,
            'createdAt' => $model->getCreatedAt(),
        ];
    }
}
