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
            'result' => [
                'summary' => $event->getModerationDetails()->getSummary(),
                'categories' => $event->getModerationDetails()
                    ->getCategories()
                    ->map(fn(Result\Category $category) => $category->getCategory())
                    ->jsonSerialize(),
            ],
            'reviewedBy' => $event->getReviewedBy() ? $this->userTranslator->__invoke($event->getReviewedBy()) : null,
            'resolvedBy' => $event->getResolvedBy() ? $this->userTranslator->__invoke($event->getResolvedBy()) : null,
            'createdAt' => $event->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            '_routes' => $this->linkTranslator->__invoke($event),
        ];
    }
}
