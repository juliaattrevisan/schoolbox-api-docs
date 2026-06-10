<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Session;

use Schoolbox\User;
use Schoolbox\OpenAi\Session\Model;

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
     * Convert an AI session model to an array for API responses representing a session summary
     *
     * @param Model $session
     * @return array
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiSession
     */
    public function __invoke(Model $session): array
    {
        $routes = $this->linkTranslator->__invoke($session);

        return [
            'id' => $session->getId(),
            'feature' => $session->getFeature(),

            // The admin UI currently needs a session-scope logFormat to choose a details renderer. This can be
            // misleading for mixed-format sessions, but each log entry has its own logFormat and is rendered individually.
            'logFormat' => $session->getDerivedLogFormat(),
            'createdAt' => $session->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'createdBy' => $session->getUser() ? $this->userTranslator->__invoke($session->getUser()) : null,
            'returnCode' => $session->getDerivedReturnCode(),
            '_routes' => $routes,
        ];
    }
}
