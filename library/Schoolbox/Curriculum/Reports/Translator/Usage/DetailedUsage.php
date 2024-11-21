<?php

namespace Schoolbox\Curriculum\Reports\Translator\Usage;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Reports\Cursor;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as CourseUnitModel;

class DetailedUsage
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private RubricUsage $rubricUsageTranslator,
        private Collection $activities,
        private Collection $lessonPlans,
        private array $capabilities,
        private Collection $inUseRubrics,
    ) {
    }

    public function __invoke(CourseUnitModel $unit, array $usage): array
    {
        return $this->translate($unit, $usage);
    }

    private function translate(CourseUnitModel $unit, array $usage): array
    {
        $rubric = [];

        $rubricCourseCapabilities = $this->capabilities['course']->map(
            function ($cap) use ($usage) {
                return call_user_func(
                    $this->rubricUsageTranslator,
                    $cap,
                    $usage[Cursor::COURSE_RUBRIC_CAPABILITY][$cap->getId()]['indicators'] ?? []
                );
            }
        );

        if (count($rubricCourseCapabilities) > 0) {
            $rubric['course'] = [
                'capabilities' => $rubricCourseCapabilities,
                '_links' => [
                    'view' => $this->getRouteIfAuthorized(
                        'rubricManager.getForm',
                        ['folderId' => $usage[Cursor::COURSE_RUBRIC_CAPABILITY]['folderId'] ?? []],
                    )
                ]
            ];
        }

        $rubricUnitCapabilities = $this->capabilities['unit']->map(
            function ($cap) use ($usage) {
                return call_user_func(
                    $this->rubricUsageTranslator,
                    $cap,
                    $usage[Cursor::COURSE_UNIT_RUBRIC_CAPABILITY][$cap->getId()]['indicators'] ?? []
                );
            }
        );

        if (count($rubricUnitCapabilities) > 0) {
            $rubric['unit'] = [
                'capabilities' => $rubricUnitCapabilities,
                '_links' => [
                    'view' => $this->getRouteIfAuthorized(
                        'rubricManager.getForm',
                        ['folderId' => $usage[Cursor::COURSE_UNIT_RUBRIC_CAPABILITY]['folderId']],
                    )
                ]
            ];
        }

        $translation = [
            'onUnit' =>
                isset($usage['unit']) &&
                $unit->getFolder()->getId() == $usage['unit']['folderId'],
        ];

        if ($this->activities->count() > 0) {
            $translation['activity'] = $this->activities->map(
                fn($activity) => [
                    'name'   => $activity->getTitle(),
                    'icon'   => $usage['activity'][$activity->getId()]['icon'],
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $activity->getId()],
                        ),
                    ],
                ]
            );
        }

        if ($this->lessonPlans->count() > 0) {
            $translation['lessonPlan'] = $this->lessonPlans->map(
                fn($lessaonPlan) => [
                    'name'   => $lessaonPlan->getTitle(),
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $lessaonPlan->getId()],
                        ),
                    ],
                ]
            );
        }

        if (!empty($rubric)) {
            $translation['rubric'] = $rubric;
        }

        if ($this->inUseRubrics->count() > 0) {
            $translation['inUseRubrics'] = $this->inUseRubrics->map(
                fn($assessment) => [
                    'name' => $assessment->getTitle(),
                    'icon' => Cursor::ACTIVITY_ICON_LIST[Cursor::COURSE_ACTIVITY_DUE_WORK],
                    'rubric' => [
                        'capabilities' => array_map(
                            fn($cap) => [
                                'name'       => $cap['name'],
                                'indicators' => $cap['indicators'] ?? [],
                            ],
                            array_values($usage[Cursor::COURSE_IN_USE_RUBRIC][$assessment->getId()])
                        ),
                        '_links' => [
                            'view' => $this->getRouteIfAuthorized(
                                'rubricManager.getForm',
                                ['folderId' => $assessment->getContextUnitSpecification()->getFolder()->getId()],
                            )
                        ]
                    ],
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $assessment->getId()],
                        )
                    ]
                ]
            );
        }

        return $translation;
    }
}
