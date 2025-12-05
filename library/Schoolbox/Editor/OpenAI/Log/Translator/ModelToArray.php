<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator;

use Schoolbox\User;
use Schoolbox\Editor\OpenAI\Log\Model;
use Schoolbox\Editor\OpenAI\Log\Translator\Format\StrategyFactory;

class ModelToArray
{
    /**
     * Constructor
     *
     * @param User\Translator\Author\ModelToArray $userTranslator
     * @param StrategyFactory $strategyFactory
     */
    public function __construct(
        private User\Translator\Author\ModelToArray $userTranslator,
        private StrategyFactory $strategyFactory,
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
        $logFormat = $log->getLogFormat();
        if (null !== $logFormat) {
            $strategy = $this->strategyFactory->makeStrategy($logFormat);
            $logData = $strategy->translate($log);
        }

        $translation = [
            'id' => $log->getId(),
            'sessionId' => $log->getSessionId(),
            'feature' => $log->getFeature(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $this->userTranslator->__invoke($log->getUser()),
            'logFormat' => $logFormat?->value,
            'data' => $logData,
        ];

        return $translation;
    }
}
