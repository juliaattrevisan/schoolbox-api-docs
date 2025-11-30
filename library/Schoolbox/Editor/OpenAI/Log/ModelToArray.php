<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log;

use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\User;

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
     * Convert an AILog model to an array for API responses.
     *
     * @param Model $log
     * @return array
     */
    public function __invoke(Model $log): array
    {
        /** This will differ between different features, or not exist at all if a feature doesn't support it (null) */
        $logData = null;
        if ($log->getFeature() === Helper::FEATURE_CHAT) {
            $logData = null; // nothing for now, but should be added later
        } elseif ($log->getFeature() === Helper::FEATURE_IMAGE) {
            $logData = self::translateLogData($log);
        }

        $translation = [
            'id' => $log->getId(),
            'sessionId' => $log->getSessionId(),
            'feature' => $log->getFeature(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $this->userTranslator->__invoke($log->getUser()),
            'data' => $logData,
        ];

        return $translation;
    }

    /**
     * Translate a saved log's inputText and outputText back into structured data for this particular feature
     * @param Model $log
     * @return array{request:string,response:array}
     */
    private static function translateLogData(Model $log): array
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
