<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator;

use Schoolbox\User;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\Editor\OpenAI\Log\Model;

class ModelToArray
{
    /**
     * Constructor
     *
     * @param User\Translator\Author\ModelToArray $userTranslator
     */
    public function __construct(
        private User\Translator\Author\ModelToArray $userTranslator,
    ) {
    }

    /**
     * Convert an AILog model to an array for API responses representing an individual log entry.
     *
     * @param Model $log
     * @return array
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiSessionLog
     */
    public function __invoke(Model $log): array
    {
        $logData = null;
        if ($log->getLogFormat() === LogFormat::AGENT_CHAT) {
            $logData = self::translateAgentChatLogData($log);
        }

        $translation = [
            'id' => $log->getId(),
            'sessionId' => $log->getSessionId(),
            'feature' => $log->getFeature(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $this->userTranslator->__invoke($log->getUser()),
            'logFormat' => $log->getLogFormat()?->value,
            'data' => $logData,
        ];

        return $translation;
    }

    /**
     * Translate a saved log's inputText and outputText back into structured data for this particular feature
     * @param Model $log
     * @return (array{type:'request',data:string}|array{type:'response',data:array})[]
     */
    public static function translateAgentChatLogData(Model $log): array
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

        /** this could be extended to allow logs of more than 1 request/response per log entry, or even other new types */
        return [
            ['type' => 'request', 'data' => $inputText],
            ['type' => 'response', 'data' => $response],
        ];
    }
}
