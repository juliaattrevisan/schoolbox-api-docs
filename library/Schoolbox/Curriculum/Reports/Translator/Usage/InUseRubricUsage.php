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
        $capabilities = $usage[Cursor::COURSE_IN_USE_RUBRIC][$assessment->getId()];
        return [
            'name' => $assessment->getTitle(),
            'icon' => Cursor::ACTIVITY_ICON_LIST[Cursor::COURSE_ACTIVITY_DUE_WORK],
            'rubric' => [
                'capabilities' => array_map(
                    function ($cap) use ($capabilities) {
                        return call_user_func(
                            $this->rubricUsageTranslator,
                            $this->models['InUseCap'][$cap],
                            $capabilities[$cap]['indicators'] ?? []
                        );
                    },
                    array_keys($capabilities ?? [])
                ),
                '_links' => [
                    'view' => $this->getRouteIfAuthorized(
                        'rubricManager.getForm',
                        ['folderId' =>
                             $assessment->getContextUnitSpecification()?->getFolder()->getId() ??
                             $assessment->getContextProject()?->getFolder()->getId()
                        ],
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
