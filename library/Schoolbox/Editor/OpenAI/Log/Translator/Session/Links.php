<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Editor\OpenAI\Log\Translator\Session;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\OpenAi\Session\Model;

/**
 * AI Log Session Links
 *
 * Set up the _routes node with a bunch of permission-based urls that $user can access
 */
class Links extends AbstractLinks
{
    /**
     * @param Model $session
     * @return array
     */
    public function __invoke(Model $session): array
    {
        $translation = [];

        $translation['getLog'] = $this->getRouteIfAuthorized('openAIUsage.getLog', [
            'sessionId' => $session->getId(),
        ]);

        return $translation;
    }
}
