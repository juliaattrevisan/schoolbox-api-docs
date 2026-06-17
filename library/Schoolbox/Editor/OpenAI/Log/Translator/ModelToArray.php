<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator;

use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\User;
use Schoolbox\OpenAi\Session\Log\Model;
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
        $logFormat = $log->getLogFormat() ? LogFormat::tryFrom($log->getLogFormat()) : null;
        $logDataStrategy = null !== $logFormat
            ? $this->strategyFactory->makeStrategy($logFormat)
            : null;

        $translation = [
            'id' => $log->getId(),
            'sessionId' => $log->getAiSessionId(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $log->getUser() ? $this->userTranslator->__invoke($log->getUser()) : null,
            'logFormat' => $logFormat?->value,
            'data' => $logDataStrategy ? [
                'input' => $logDataStrategy->translateInput($log),
                'output' => $logDataStrategy->translateOutput($log),
            ] : null,
        ];

        return $translation;
    }
}
