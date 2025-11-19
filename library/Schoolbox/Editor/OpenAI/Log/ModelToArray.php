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
            $logData = \Schoolbox\OpenAi\Agent\ImageGeneration::translateLogData($log);
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
}
