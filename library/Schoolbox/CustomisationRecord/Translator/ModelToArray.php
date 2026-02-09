<?php

namespace Schoolbox\CustomisationRecord\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Assessment\Participant\Model;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplModel;
use Schoolbox\Learning\Assessment\DataModel\Project\Model as ProjectModel;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use Schoolbox\User\Translator\ModelToArray as UserModelToArray;

class ModelToArray
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private UserModelToArray $userTranslator,
        private MapperProxy $assessmentMapperProxy,
    ) {
    }

    public function __invoke(Model $participant): array
    {
        $plan = $participant->getPlan();
        $originalActivity = $plan->getAssessment()->getOriginalAssessment() ?? $plan->getAssessment();

        try {
            $customiseActivityView = $plan->isCustomParticipation() ?
                $this->getRouteIfAuthorized(
                    'learningAssessment.getAssessment',
                    [
                        'assessmentId' => $plan->getAssessment()->getId()
                    ],
                ) :
                $this->getRouteIfAuthorized(
                    'learningAssessment.getAssessmentParticipationForm',
                    [
                        'assessment' => $this->assessmentMapperProxy->findById($plan->getAssessment()->getId()),
                        'assessmentId' => $plan->getAssessment()->getId()
                    ],
                );
        } catch (Deleted $e) {
            $customiseActivityView = null;
        }

        /** @var UnitImplModel $context */
        $context = $plan->getAssessment()->getContextUnitImplementation();
        if (null === $context) {
            /* Assessment in a project, then look at the project context
             */
            $context = $plan->getAssessment()->getContextProject()->getAssessmentModel()->getContextUnitImplementation();
        }

        return [
            'created_at' => $participant->getCreatedAt()->toRfc3339String(),
            'user' => call_user_func($this->userTranslator, $participant->getUser()),
            'customised_activity' => [
                'id' => $plan->getId(),
                'title' => $plan->getName() ?? $plan->getAssessment()->getTitle(),
                '_links' => [
                    'view' => $customiseActivityView
                ]
            ],
            'original_activity' => [
                'id' => $originalActivity->getId(),
                'title' => $originalActivity->getTitle(),
                '_links' => [
                    'view' => $this->getRouteIfAuthorized(
                        'learningAssessment.getAssessment',
                        ['assessmentId' => $originalActivity->getId()],
                    )
                ]
            ],
            'type' => [
                'title' => $plan->isCustomParticipation() ? 'New Activity' : 'Modified Activity'
            ],
            'unit' => [
                'title' => $context?->getTitle(),
            ],
            'class' => [
                'title' => $context?->getFolder()?->getTitle()
            ]
        ];
    }
}
