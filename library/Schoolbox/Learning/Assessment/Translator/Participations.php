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
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;

/**
 * Translator for translating a Participant model into an array.
 */
class Participations
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator      $routeGenerator
     * @param Authorizer     $authorizer
     * @param User           $currentUser
     *
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    /**
     * @param Participant $participation
     * @return array
     */
    public function __invoke(Participant $participation): array
    {
        $assess = $participation->getAssessable();
        $participant = $participation->getUser();
        return [
            'id'         => $participation->getId(),
            'user'       => $participant,
            'plan'       => call_user_func(new ParticipationPlans(), $participation->getPlan()),
            'createdAt'  => $participation->getCreatedAt()?->toRfc3339String(),
            'updatedAt'  => $participation->getUpdatedAt()?->toRfc3339String(),
            'excludedAt' => $participation->getExcludedAt()?->toRfc3339String(),
            '_links'     => [
                'exclude' => $this->getRouteIfAuthorized('learningAssessment.postExclude', [
                    'assessment' => $assess,
                    'assessmentId' => $assess->getId(),
                    'student' => $participant,
                    'studentId'    => $participant->getId(),
                ]),
                'include' => $this->getRouteIfAuthorized('learningAssessment.postInclude', [
                    'assessment' => $assess,
                    'assessmentId' => $assess->getId(),
                    'student' => $participant,
                    'studentId'    => $participant->getId(),
                ])
            ]
        ];
    }
}
