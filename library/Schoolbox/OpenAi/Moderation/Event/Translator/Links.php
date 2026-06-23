<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Event\Translator;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\OpenAi\Moderation\Event\Model;

/**
 * AI Interaction Moderation Event Links
 *
 * Set up the _routes node with a bunch of permission-based urls that $user can access
 */
class Links extends AbstractLinks
{
    /**
     * @param Model $event
     * @return array
     */
    public function __invoke(Model $event): array
    {
        $translation = [];

        $translation['postDismissEvent'] = $this->getRouteIfAuthorized('adminAiModeration.postDismissEvent', [
            'eventId' => $event->getId(),
        ]);

        $translation['postConfirmEvent'] = $this->getRouteIfAuthorized('adminAiModeration.postConfirmEvent', [
            'eventId' => $event->getId(),
        ]);

        $translation['postResolveEvent'] = $this->getRouteIfAuthorized('adminAiModeration.postResolveEvent', [
            'eventId' => $event->getId(),
        ]);

        return $translation;
    }
}
