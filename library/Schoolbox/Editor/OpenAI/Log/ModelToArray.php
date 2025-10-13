<?php

/*
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log;

class ModelToArray
{
    /**
     * Convert an AILog model to an array for API responses.
     *
     * @param Model $log
     * @return array
     */
    public function __invoke(Model $log): array
    {
        return [
            'id' => $log->getId(),
            'sessionId' => $log->getSessionId(),
            'createdAt' => $log->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'userId' => $log->getUser()->getId(),
            'prompt' => $log->getPrompt(),
            'inputTokens' => $log->getInputTokens(),
            'outputTokens' => $log->getOutputTokens(),
            'model' => $log->getModel(),
            'endpoint' => $log->getEndpoint(),
            'feature' => $log->getFeature(),
            'response' => $log->getResponse(),
            'returnCode' => $log->getReturnCode(),
            'errorMessage' => $log->getErrorMessage(),
        ];
    }
}
