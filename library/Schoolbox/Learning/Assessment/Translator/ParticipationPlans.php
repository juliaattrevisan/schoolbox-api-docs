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
        $return = [
            'id' => $plan->getId(),
            'name' => $plan->getName(),
            'description' => $plan->getDescription(),
            'isStatic' => $plan->isStatic(),
            'publishDate' => $plan->getPublishDate()?->toRfc3339String(),
            'openDate' => $plan->getOpenDate()?->toRfc3339String(),
            'closeDate' => $plan->getCloseDate()?->toRfc3339String(),
            'dueDate' => $plan->getDueDate()?->toRfc3339String(),
            'createdAt' => $plan->getCreatedAt()?->toRfc3339String(),
            'updatedAt' => $plan->getUpdatedAt()?->toRfc3339String(),
            'deletedAt' => $plan->getDeletedAt()?->toRfc3339String(),
            '_links' => [
                'modify' =>
                    $this->getRouteIfAuthorized(
                        'learningAssessment.getUpdateAssessmentParticipationLearningPlan',
                        [
                            'assessmentId' => $plan->getAssessable()->getId(),
                            'assessment' => $plan->getAssessable(),
                            'planId' => $plan->getId(),
                            'plan' => $plan,
                        ]
                    )
            ],
        ];

        /* This will only be attached if the assessment is a customised assessment, we are making the assumption that
         * customised assessments can't have customisations for the fear of inception
         */
        if (null === $plan->getName()) {
            $assessable = $plan->getAssessable();
            $return['assessable'] = [
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
            ];
        }

        return $return;
    }
}
