<?php

namespace Schoolbox\Curriculum\Reports\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Curriculum\Reports\Cursor;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as CourseUnitModel;
use Schoolbox\Collection;

class DetailedUsage
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private Collection $capabilities,
        private Collection $unitCapabilities,
    ) {
    }

    public function __invoke(CourseUnitModel $unit, array $usage): array
    {
        return $this->translate($unit, $usage);
    }

    private function translate(CourseUnitModel $unit, array $usage): array
    {

        return [
            'onUnit'   => isset($usage['unit']),
            'activity' => array_map(
                fn($activity) => [
                    'name'   => $activity['title'],
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $activity['id']],
                        ),
                    ],
                ],
                $usage['activity'] ?? []
            ),
            'lessonPlan' => array_map(
                fn($lp) => [
                    'name'   => $lp['title'],
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $lp['id']],
                        ),
                    ],
                ],
                $usage[Cursor::COURSE_ACTIVITY_LESSON_PLAN] ?? []
            ),
            'rubric' => [
                'course' => [
                    'capabilities' =>
                        $this->capabilities->map(
                            fn ($cap) =>
                            [
                                'name' => $cap->getName(),
                                'indicators' => array_map(
                                    fn($indicator) => [
                                        'name' => $indicator['name'],
                                    ],
                                    $usage[Cursor::COURSE_RUBRIC_CAPABILITY][$cap->getId()]
                                ),
                                '_links' => [
                                    'view' => $this->getRouteIfAuthorized(
                                        'rubricManager.getForm',
                                        ['folderId' => $cap->getParentId()],
                                    )
                                ]
                            ]
                        )
                ],
                'unit' => [
                    'capabilities' =>
                        $this->unitCapabilities->map(
                            fn ($cap) =>
                            [
                                'name' => $cap->getName(),
                                'indicators' => array_map(
                                    fn($indicator) => [
                                        'name' => $indicator['name'],
                                    ],
                                    $usage[Cursor::COURSE_UNIT_RUBRIC_CAPABILITY][$cap->getId()]
                                ),
                                '_links' => [
                                    'view' => $this->getRouteIfAuthorized(
                                        'rubricManager.getForm',
                                        ['folderId' => $cap->getParentId()],
                                    )
                                ]
                            ]
                        )
                ]
            ]
        ];
    }
}
