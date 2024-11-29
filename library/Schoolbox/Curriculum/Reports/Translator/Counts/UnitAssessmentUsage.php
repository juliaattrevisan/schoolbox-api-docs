<?php

namespace Schoolbox\Curriculum\Reports\Translator\Counts;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Curriculum\Reports\Cursor;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as CourseUnitModel;
use Schoolbox\Curriculum\Node\Model as CurriculumNodeModel;
use Schoolbox\User;

/**
 * Course unit model to array translator for curriculum coverage report
 */
class UnitAssessmentUsage
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator     $routeGenerator
     * @param Authorizer    $authorizer
     * @param User          $currentUser
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
    ) {
    }

    /**
     * @param CourseUnitModel       $unit
     * @param CurriculumNodeModel   $node
     * @param array                 $counts
     * @return array
     */
    public function __invoke(CourseUnitModel $unit, CurriculumNodeModel $node, array $counts): array
    {
        return $this->translate($unit, $node, $counts);
    }

    private function translate(CourseUnitModel $unit, CurriculumNodeModel $node, array $counts): array
    {
        $unitUsage = $counts[$node->getId()][$unit->getCourse()->getId()][$unit->getFolder()->getId()] ?? null;
        $unitCount =
            $unitUsage[Cursor::COURSE_UNIT] ?? 0;
        $activityCount =
            ($unitUsage[Cursor::COURSE_ACTIVITY_TASK] ?? 0) +
            ($unitUsage[Cursor::COURSE_ACTIVITY_QUIZ] ?? 0) +
            ($unitUsage[Cursor::COURSE_ACTIVITY_LTI] ?? 0) +
            ($unitUsage[Cursor::COURSE_ACTIVITY_DUE_WORK] ?? 0) +
            ($unitUsage[Cursor::COURSE_ACTIVITY_PROJECT] ?? 0) +
            ($unitUsage[Cursor::COURSE_PROJECT_ACTIVITY_TASK] ?? 0) +
            ($unitUsage[Cursor::COURSE_PROJECT_ACTIVITY_QUIZ] ?? 0) +
            ($unitUsage[Cursor::COURSE_PROJECT_ACTIVITY_LTI] ?? 0) +
            ($unitUsage[Cursor::COURSE_PROJECT_ACTIVITY_DUE_WORK] ?? 0);
        $lessonPlanCount =
            ($unitUsage[Cursor::COURSE_ACTIVITY_LESSON_PLAN] ?? 0) +
            ($unitUsage[Cursor::COURSE_PROJECT_ACTIVITY_LESSON_PLAN] ?? 0);

        $rubricCount = array_sum(
            [
                ($unitUsage[Cursor::COURSE_UNIT_RUBRIC_CAPABILITY] ?? 0),
                ($unitUsage[Cursor::COURSE_UNIT_RUBRIC_INDICATOR] ?? 0),
                ($counts[$node->getId()][$unit->getCourse()->getId()][Cursor::COURSE_RUBRIC_CAPABILITY] ?? 0),
                ($counts[$node->getId()][$unit->getCourse()->getId()][Cursor::COURSE_RUBRIC_INDICATOR] ?? 0),
            ]
        );
        $inUseRubricCount = array_sum(
            [
                ($unitUsage[Cursor::COURSE_IN_USE_RUBRIC] ?? 0),
                ($unitUsage[Cursor::COURSE_PROJECT_IN_USE_RUBRIC] ?? 0)
            ]
        );

        return [
            'id'    => $unit->getFolder()->getId(),
            'name'  => $unit->getTitle(),
            'usage' => [
                'unit' => [
                    'count' => $unitCount
                ],
                'activity' => [
                    'count' => $activityCount
                ],
                'lessonPlan' => [
                    'count' => $lessonPlanCount
                ],
                'rubric' => [
                    'count' => $rubricCount
                ],
                'inUseRubric' => [
                    'count' => $inUseRubricCount
                ]
            ],
            '_links' => [
                'modify' => $this->getRouteIfAuthorized(
                    'folder.getFolderModify',
                    ['id' => $unit->getFolder()->getId(), 'folder' => $unit->getFolder()],
                ),
                'delete' => $this->getRouteIfAuthorized(
                    'folder.deleteFolder',
                    ['id' => $unit->getFolder()->getId(), 'folder' => $unit->getFolder()],
                ),
            ]
        ];
    }
}
