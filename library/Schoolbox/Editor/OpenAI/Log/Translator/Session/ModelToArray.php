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
        $routes = $this->linkTranslator->__invoke($log);

        /**
         * Note: $log only represents session-specific stuff, values representing an individual log
         * such as $log->getPrompt() or $log->getOutputText() are intentially omitted to reduce memory overhead
         */

        return [
            'id' => $log->getSessionId(),
            'feature' => $log->getFeature(),
            'logFormat' => $log->getLogFormat(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $log->getUser() ? $this->userTranslator->__invoke($log->getUser()) : null,
            'returnCode' => $log->getReturnCode(),
            '_routes' => $routes,
        ];
    }
}
