<?php

namespace Schoolbox\Learning\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DataModel\Translator\LessonPlan;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;

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
     * @var IAssessable[]|Collection
     */
    private $visibleLessonPlans;

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

        $this->assessmentDataSource = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $student = $this->getUser();

        $visibility                = new Visibility($this->getAccess());
        $this->visibleLessonPlans = $this->assessmentDataSource
            ->findAllByStudentBetweenDates(
                $student,
                true,
                \Alaress_Date_Format::now()->addDays(-$this->range),
                \Alaress_Date_Format::now()->addDays($this->range)
            )
            ->filter(static function (IAssessable $assessable) use ($student, $visibility) {
                // Only show lesson plans
                if (!$assessable instanceof LessonPlan) {
                    return false;
                }
                // Only show work that the student has permission to view
                $participation = $assessable->getParticipantIfExtant($student);
                return $participation
                    && ($participation->isResponseRequired() || $participation->isResubmissionRequired())
                    && $visibility->isVisibleToUser($assessable, $student, false);
            })
            ->sortByCallback(new DueDateSortStrategy($student));

        return true;
//        return \count($this->visibleLessonPlans) > 0;
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
