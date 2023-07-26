<?php

namespace Schoolbox\Learning\Component\Homepage;

use Alaress\Database\Connector\IConnector;
use Component_Homepage_ControllerAbstract;
use Schoolbox\Collection;
use Schoolbox\External\SubjectCollection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Field\Model as LessonPlanFieldModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LessonPlan\Model as LessonPlanModel;
use Schoolbox\Learning\Assessment\Visibility;

class CurrentLessonPlanController extends Component_Homepage_ControllerAbstract
{
    // Should match the DB value
    private const RANGE_DEFAULT = 0;

    /**
     * The number of days to find lesson plans either side of today
     *
     * @var int
     */
    private int $range;

    /**
     * The lesson plans visible to the user
     *
     * @var GenericCollection|IAssessable[]
     */
    private GenericCollection|array $visibleLessonPlans;

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

        $dateStart = \Alaress_Date_Format::now()->startOfDay()->addDays(-$this->range);
        $dateEnd = \Alaress_Date_Format::now()->endOfDay()->addDays($this->range);

        $assessmentDataSource = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $student = $this->getUser();

        /**
         * If parent role, or user has no subjects
         * exit early
         */
        if ($student->getRole()->isParent()) {
            return false;
        }

        /** @var IConnector $database */
        $database = $this->getSitePage()->getDi()->get('databaseInternal');
        $pb          = $database
            ->buildPredicateBuilder('')
            ->betweenDates('assessment.due_date', $dateStart, $dateEnd)
            ->equals('mv_assessment_relation.folder_id', $this->getContext()->getFolder()->getId());

        // set visibility check to be student for student...
        if ($this->getUser()->getRole()->isStudent()) {
            $pb->equals('lesson_plan.visibility', 'student');
        }
        $whereClause = implode(' AND ', $pb->getWhere());
        $query       = "
            SELECT * FROM assessment
            JOIN lesson_plan ON (assessment.id = lesson_plan.assessment_id)
            JOIN mv_assessment_relation on assessment.id = mv_assessment_relation.assessment_id
            WHERE {$whereClause}
            ";
        $statement   = $database->executeQuery($query, $pb->getParams());

        $assessmentIds = [];
        while ($row = $statement->fetch()) {
            $assessmentIds[] = $row['assessment_id'];
        }

        $this->visibleLessonPlans = $assessmentDataSource->findAllByIds($assessmentIds)->sortBy('dueAt', 'desc');

        return true;
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
            '@learning/component/lessonPlanHomepage.twig',
            [
                'range'       => $this->range,
                'lessonPlans' => $this->visibleLessonPlans,
            ]
        );
    }
}
