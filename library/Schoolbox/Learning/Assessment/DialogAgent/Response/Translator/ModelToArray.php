<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Response\Translator;

use Schoolbox\Learning\Assessment\DialogAgent\Response\Model;
use Schoolbox\Learning\Assessment\DialogAgent\Response\Lookup;

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
        $status = (new Lookup\Status())->calculate($response);

        // TODO: These routes also are possibly meant to pull in info from $status["actions"], but that should ideally occur in the module if possible
        $routes = $this->linkTranslator->__invoke($response);

        return [
            'id' => $response->getId(),
            'status' => $status["status"],
            'assistanceRequestedAt' => $response->getAssistanceRequestedAt()?->toRfc3339String(),
            'submittedAt' => $response->getSubmittedAt()?->toRfc3339String(),
            '_routes' => $routes
        ];
    }
}
