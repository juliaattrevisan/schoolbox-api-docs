<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Session;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Editor\OpenAI\Log\Model;

/**
 * AI Log Session Links
 *
 * Set up the _routes node with a bunch of permission-based urls that $user can access
 */
class Links extends AbstractLinks
{
    /**
     * @param Model $log
     * @return array
     */
    public function __invoke(Model $log): array
    {
        $translation = [];

        $translation['getLog'] = $this->getRouteIfAuthorized('openAIUsage.getLog', [
            'sessionId' => $log->getSessionId()
        ]);

        return $translation;
    }
}
