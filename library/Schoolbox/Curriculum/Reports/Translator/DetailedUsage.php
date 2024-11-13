<?php

namespace Schoolbox\Curriculum\Reports\Translator;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as CourseUnitModel;

class DetailedUsage
{
    use GetRouteIfAuthorized;

    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
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
                fn($activity, $key) => [
                    'name'   => $activity,
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $key],
                        ),
                    ],
                ],
                $usage['activity'],
                array_keys($usage['activity'])
            ),
            'lessonPlan' => array_map(
                fn($lp, $key) => [
                    'name'   => $lp,
                    '_links' => [
                        'view' => $this->getRouteIfAuthorized(
                            'learningAssessment.getAssessment',
                            ['assessmentId' => $key],
                        ),
                    ],
                ],
                $usage['lessonPlan'],
                array_keys($usage['lessonPlan'])
            )
        ];
    }
}
