<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Schoolbox\Learning\Assessment\Plan;

/**
 * Translator for translating a Participant Plan model into an array.
 */
class ParticipationPlans
{
    /**
     * @param Plan $plan
     * @return array
     */
    public function __invoke(Plan $plan): array|null
    {
        return [
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'description' => $plan->getDescription(),
            'isDynamic' => $plan->getIsDynamic(),
            'publishDate' => $plan->getPublishDate()?->toRfc3339String(),
            'openDate' => $plan->getOpenDate()?->toRfc3339String(),
            'closeDate' => $plan->getCloseDate()?->toRfc3339String(),
            'dueDate' => $plan->getDueDate()?->toRfc3339String(),
            'createdAt' => $plan->getCreatedAt()?->toRfc3339String(),
            'updatedAt' => $plan->getUpdatedAt()?->toRfc3339String(),
            'deletedAt' => $plan->getDeletedAt()?->toRfc3339String(),
        ];
    }
}
