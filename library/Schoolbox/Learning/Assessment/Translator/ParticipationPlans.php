<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Learning\Assessment\Plan;
use Schoolbox\User;

/**
 * Translator for translating a Participant Plan model into an array.
 */
class ParticipationPlans
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    /**
     * @param Plan $plan
     * @return ?array
     */
    public function __invoke(Plan $plan): array|null
    {
        $assessable = $plan->getAssessable();
        return [
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'assessable' => [
                'id' => $assessable->getId(),
                'title' => $assessable->getTitle(),
                '_links' => [
                    'modify' => $this->getRouteIfAuthorized(
                        'learningAssessment.getUpdateAssessmentForm',
                        [
                            'assessment' => $assessable,
                            'assessmentId' => $assessable->getId(),
                        ]
                    )
                ]
            ],
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
