<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Session;

use Schoolbox\User;
use Schoolbox\Editor\OpenAI\Helper;
use Schoolbox\Editor\OpenAI\Log\LogFormat;
use Schoolbox\Editor\OpenAI\Log\Model;

class ModelToArray
{
    /**
     * Constructor
     *
     * @param User\Translator\Author\ModelToArray $userTranslator
     * @param Links $linkTranslator
     */
    public function __construct(
        private User\Translator\Author\ModelToArray $userTranslator,
        private Links $linkTranslator,
    ) {
    }

    /**
     * Convert an AILog model to an array for API responses representing a session summary
     *
     * @param Model $log
     * @return array
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiSession
     */
    public function __invoke(Model $log): array
    {
        // TODO: log types will need to be stored in the DB due to dialog agent having multiple ways to render, currently not implemented yet
        $logFormat = null;
        if (Helper::FEATURE_IMAGE === $log->getFeature() || Helper::FEATURE_DIALOGAGENT === $log->getFeature()) {
            $logFormat = LogFormat::AGENT_CHAT;
        }

        $routes = $this->linkTranslator->__invoke($log);

        return [
            'id' => $log->getSessionId(),
            'feature' => $log->getFeature(),
            'logFormat' => $logFormat?->value,
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $this->userTranslator->__invoke($log->getUser()),
            'returnCode' => $log->getReturnCode(),
            '_routes' => $routes,
        ];
    }
}
