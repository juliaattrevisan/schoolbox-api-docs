<?php

namespace Schoolbox\Curriculum\Reports\Translator\Counts;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\GetRouteIfAuthorized;
use Aura\Router\Generator;
use Schoolbox\Curriculum\Node\Model as CurriculumNodeModel;
use Schoolbox\Curriculum\Reports\Cursor;
use Schoolbox\Curriculum\Reports\Helper;
use Schoolbox\Curriculum\Reports\Translator\Counts\UnitAssessmentUsage as UnitAssessmentUsageTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationModel;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;

/**
 * Course spec model to array translator for curriculum coverage report
 */
class CourseUsage
{
    use GetRouteIfAuthorized;

    /**
     * Constructor
     *
     * @param Generator                     $routeGenerator
     * @param Authorizer                    $authorizer
     * @param User                          $currentUser
     * @param Helper                        $helper
     * @param UnitAssessmentUsageTranslator $unitAssessmentUsageUsageTranslator
     * @param array                         $courseUnitSpec
     */
    public function __construct(
        protected Generator $routeGenerator,
        protected Authorizer $authorizer,
        protected User $currentUser,
        private Helper $helper,
        private UnitAssessmentUsageTranslator $unitAssessmentUsageUsageTranslator,
        private array $courseUnitSpec,
    ) {
    }

    /**
     * @param CourseSpecificationModel $course
     * @param CurriculumNodeModel      $node
     * @param array                    $counts
     * @return array
     * @throws Exception
     */
    public function __invoke(
        CourseSpecificationModel $course,
        CurriculumNodeModel $node,
        array $counts
    ): array {
        return $this->translate($course, $node, $counts);
    }

    /**
     * @param CourseSpecificationModel $course
     * @param CurriculumNodeModel      $node
     * @param array                    $counts
     * @return array
     * @throws Exception
     */
    private function translate(
        CourseSpecificationModel $course,
        CurriculumNodeModel $node,
        array $counts
    ): array {

        $courseCount = $counts[$node->getId()][$course->getId()][Cursor::COURSE_COURSE_SPECIFICATION] ?? 0;
        return [
            'id'    => $course->getId(),
            'name'  => $course->getTitle(),
            'usage' => [
                'course' => [
                    'count' => $courseCount
                ]
            ],
            'units' => $this->helper->filterUserPermission(
                $this->courseUnitSpec[$course->getId()],
                $this->currentUser
            )?->map(
                function ($unit) use ($node, $counts) {
                    return call_user_func($this->unitAssessmentUsageUsageTranslator, $unit, $node, $counts);
                }
            ),
            '_links'        => [
                'modify' => $this->getRouteIfAuthorized(
                    'course.modifyCourse',
                    ['id' => $course->getId(), 'course' => $course],
                ),
                'delete' => $this->getRouteIfAuthorized(
                    'course.deleteCourseSpecification',
                    ['id' => $course->getId(), 'course' => $course],
                ),
            ]
        ];
    }
}
