<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Translator;

use Schoolbox\Learning\Assessment\DialogAgent\Response\Model;

/**
 * Translator for converting Dialog Agent Response to an array representation, for
 * outputting in a JSON response.
 */
class ModelToArray
{
    /**
     * @param Links $linkTranslator
     */
    public function __construct(
        private Links $linkTranslator,
    ) {
    }


    /**
     * @param Model $response
     * @return array
     */
    public function __invoke(Model $response): array
    {
        $routes = $this->linkTranslator->__invoke($response);

        return [
            'id' => $response->getId(),
            'status' => $response->getCurrentStatus(),
            'assistanceRequestedAt' => $response->getAssistanceRequestedAt()?->toRfc3339String(),
            'submittedAt' => $response->getSubmittedAt()?->toRfc3339String(),
            'startedAt' => $response->getStartedAt()?->toRfc3339String(),
            '_routes' => $routes,
        ];
    }
}
