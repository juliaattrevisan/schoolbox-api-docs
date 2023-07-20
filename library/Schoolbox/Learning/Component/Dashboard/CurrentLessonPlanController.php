<?php

namespace Schoolbox\Learning\Component\Dashboard;

use Alaress\Database\Connector\IConnector;
use Component_Dashboard_ControllerAbstract;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;

class CurrentLessonPlanController extends Component_Dashboard_ControllerAbstract
{
    // Should match the DB value
    private const RANGE_DEFAULT = 0;

    /**
     * The number of days to find lesson plans either side of today
     *
     * @var int
     */
    private $range;

    /**
     * The upcoming Quiz + DueWork that are visible to this Student
     *
     * @var array
     */
    private array $visibleLessonPlans = [];

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentDataSource;

    /**
     * Business logic to collate data ready for output
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function execute(): bool
    {

        $this->range = is_numeric($this->getConfig('range')) ? $this->getConfig('range') : self::RANGE_DEFAULT;
        $this->range = 60;

        $dateStart = \Alaress_Date_Format::now()->addDays(-$this->range);
        $dateEnd = \Alaress_Date_Format::now()->addDays($this->range);

        $this->assessmentDataSource = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $student = $this->getUser();

        /** @var IConnector $database */
        $database = $this->getSitePage()->getDi()->get('databaseInternal');
        $pb          = $database
            ->buildPredicateBuilder('')
            ->betweenDates('assessment.due_date', $dateStart, $dateEnd);
        if ($this->getUser()->getRole()->isStudent()) {
            $pb->equals('lesson_plan.visibility', 'student');
        }
        $whereClause = implode(' AND ', $pb->getWhere());
        $query       = "
            SELECT * FROM assessment
            JOIN lesson_plan ON (assessment.id = lesson_plan.assessment_id)
            WHERE {$whereClause}
            ";
        $statement   = $database->executeQuery($query, $pb->getParams());

        $assessmentIds = [];
        while ($row = $statement->fetch()) {
            $assessmentIds[] = $row['assessment_id'];
        }

        $plans = $this->assessmentDataSource->findAllByIds($assessmentIds);

        $route = 'learningAssessment.getSummary';
        foreach ($plans as $lessonPlan) {
            // are all fields visible?

            $params =  [
                'assessmentId' => $lessonPlan->getId(),
                'assessment'   => $lessonPlan,
                'studentId'    => $student->getId(),
                'student'      => $student,
            ];

            //build array
            $this->visibleLessonPlans[] = [
                'title' => $lessonPlan->getTitle(),
                'dueDate' => $lessonPlan->getDueAt(),
                'className' => $lessonPlan->getContext()->getFolder()->getTitle(),
                'classColour' => $lessonPlan->getContext()->getFolder()->getColour(),
                'url' => $this->getRouteGenerator()->generate($route, $params)
            ];
        }

//        return true;
        return \count($this->visibleLessonPlans) > 0;
        /**
         * return array should be similar to:
         * ```
         * [{
         * 'title': 'Lesson Plan Title',
         * 'dueDate': '2023-07-21 11:13:56Z',
         * 'class': 'class Name',
         * 'classColour': '#FFFFFF',
         * 'url': 'https://vagrant.dev.schoolbox.cloud/learning/assessments/434/1'
         * },
         * ]
         * ```
         *
         */
    }

    /**
     * Output the data we collected in exec()
     *
     * @param  String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learning/component/lessonPlanDashboard.twig',
            [
                'range'       => $this->range,
                'lessonPlans' => $this->visibleLessonPlans,
            ]
        );
    }
}
