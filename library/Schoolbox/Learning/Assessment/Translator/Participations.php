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
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use Schoolbox\Collection;

/**
 * Translator for translating a Participant model into an array.
 */
class Participations
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator          $routeGenerator
     * @param Authorizer         $authorizer
     * @param User               $currentUser
     * @param ParticipationPlans $planTranslator
     * @param ?Collection        $customisedAssessments
     *
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private ParticipationPlans $planTranslator,
        private ?Collection $customisedAssessments = null,
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

        $customisedPlan = null;
        /* If the student is excluded from the original assessment, is she/he part of a customised assessment?
         */
        if (null !== $participation->getExcludedAt()) {
            /* Only grab the first match as student should only have maximum of one customised assessment
             */
            $customisation = $this->customisedAssessments?->filter(function (IAssessable $customisation) use ($participant) {
                return $customisation->isParticipant($participant);
            })->first();
            if (null !== $customisation) {
                $customisedPlan = call_user_func($this->planTranslator, $customisation->getParticipant($participant)->getPlan());
            }
        }

        return [
            'id'         => $participation->getId(),
            'user'       => $participant,
            'plan'       => call_user_func($this->planTranslator, $participation->getPlan()),
            'createdAt'  => $participation->getCreatedAt()?->toRfc3339String(),
            'updatedAt'  => $participation->getUpdatedAt()?->toRfc3339String(),
            'excludedAt' => $participation->getExcludedAt()?->toRfc3339String(),
            /* Plan of the customised assessment which are created from the original assessment
             */
            'customisedPlan' => $customisedPlan,
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
