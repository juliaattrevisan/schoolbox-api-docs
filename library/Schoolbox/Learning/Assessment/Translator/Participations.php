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
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Schoolbox\Editor\OpenAI\Helper;
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
     * @param ?CacheInterface    $aiCache
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private ParticipationPlans $planTranslator,
        private ?Collection $customisedAssessments = null,
        private ?CacheInterface $aiCache = null,
    ) {
    }

    /**
     * @param Participant $participation
     * @return array
     * @throws InvalidArgumentException
     */
    public function __invoke(Participant $participation): array
    {
        $assess = $participation->getAssessable();
        $participant = $participation->getUser();

        $customisedPlan = null;
        $customisation = null;
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

        $cacheKey = sprintf(
            Helper::DIFFERENTIATION_PROFILE_CACHE_KEY,
            $assess->getId(),
            $participant->getId()
        );

        return [
            'id'         => $participation->getId(),
            'user'       => $participant,
            'plan'       => call_user_func($this->planTranslator, $participation->getPlan()),
            'cachedSuggestedCustomisation' => $this->aiCache?->get($cacheKey),
            'createdAt'  => $participation->getCreatedAt()?->toRfc3339String(),
            'updatedAt'  => $participation->getUpdatedAt()?->toRfc3339String(),
            'excludedAt' => $participation->getExcludedAt()?->toRfc3339String(),
            /* Plan of the customised assessment which are created from the original assessment
             */
            'customisedPlan' => $customisedPlan,
            /* Check if the participant has any interaction with the assessment (ie responses or feedbacks)
             */
            'hasInteraction' =>
                null !== $customisation ?
                    ($customisation->getParticipant($participant)->getAllResponses()->count() > 0 || $customisation->getParticipant($participant)->getAllFeedbacks()->count() > 0) :
                    ($participation->getAllResponses()->count() > 0 || $participation->getAllFeedbacks()->count() > 0),
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
                ]),
                'differentiationProfile' => $this->getRouteIfAuthorized(
                    'differentiationProfiles.viewDifferentiationProfileData',
                    [
                        'student' => $participant,
                        'studentId' => $participant->getId()
                    ],
                ),
                'customisationRecord' => $this->getRouteIfAuthorized(
                    'customisationRecord.viewRecordByStudent',
                    [
                        'studentId' => $participant->getId()
                    ],
                ),
            ]
        ];
    }
}
