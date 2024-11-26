<?php

namespace Schoolbox\Curriculum\Reports\Translator\Usage;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Curriculum\Reports\Cursor;
use Schoolbox\User;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;

class InUseRubricUsage
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private array $models,
        private RubricUsage $rubricUsageTranslator,
    ) {
    }

    public function __invoke(AssessmentModel $assessment, array $usage): array
    {
        return $this->translate($assessment, $usage);
    }

    private function translate(AssessmentModel $assessment, array $usage): array
    {
        return [
            'name' => $assessment->getTitle(),
            'icon' => Cursor::ACTIVITY_ICON_LIST[Cursor::COURSE_ACTIVITY_DUE_WORK],
            'rubric' => [
                'capabilities' => $this->models['InUseCap']->map(
                    function ($cap) use ($usage, $assessment) {
                        return call_user_func(
                            $this->rubricUsageTranslator,
                            $cap,
                            $usage[Cursor::COURSE_IN_USE_RUBRIC][$assessment->getId()][$cap->getId()]['indicators'] ?? []
                        );
                    }
                ),
                '_links' => [
                    'view' => $this->getRouteIfAuthorized(
                        'rubricManager.getForm',
                        ['folderId' => $assessment->getContextUnitSpecification()->getFolder()->getId()],
                    )
                ],
            ],
            '_links' => [
                'view' => $this->getRouteIfAuthorized(
                    'learningAssessment.getAssessment',
                    ['assessmentId' => $assessment->getId()],
                )
            ]
        ];
    }
}
