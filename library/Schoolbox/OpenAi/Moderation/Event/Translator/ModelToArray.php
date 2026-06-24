<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Event\Translator;

use Schoolbox\User;
use Schoolbox\OpenAi\Moderation\Event\Model;
use Schoolbox\OpenAi\Moderation\Result;

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
     * Convert an AI Interaction Moderation event model to an array for API responses.
     *
     * @param Model $event
     * @return array
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/moderationEvent.ts AiModerationEvent
     */
    public function __invoke(Model $event): array
    {
        return [
            'id' => $event->getId(),
            'status' => $event->getStatus(),
            /* note: this is the entire moderation result for now, may need to reduce this if more than superusers have access to it in the future */
            'result' => $event->getModerationDetails()->jsonSerialize(),
            'reviewedBy' => $event->getReviewedBy() ? $this->userTranslator->__invoke($event->getReviewedBy()) : null,
            'resolvedBy' => $event->getResolvedBy() ? $this->userTranslator->__invoke($event->getResolvedBy()) : null,
            'createdAt' => $event->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            '_routes' => $this->linkTranslator->__invoke($event),
        ];
    }
}
