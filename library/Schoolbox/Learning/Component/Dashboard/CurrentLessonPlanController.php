<?php

namespace Schoolbox\Learning\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
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
        $student = $this->getUser();

        /**
         * If parent role, or user has no subjects
         * exit early
         */
        if ($student->getRole()->isParent() || 0 === $student->getSubjectCollection()->count()) {
            return false;
        }

        $lessonPlanMapper = LessonPlanMapper::getInstance();
        $assessmentDataSource = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $this->range = is_numeric($this->getConfig('range')) ? $this->getConfig('range') : self::RANGE_DEFAULT;

        $dateStart = \Alaress_Date_Format::now()->startOfDay()->addDays(-$this->range);
        $dateEnd = \Alaress_Date_Format::now()->endOfDay()->addDays($this->range);
        $publishDate = \Alaress_Date_Format::now();

        // grab the assessmentID for all lesson plans from the students subject list
        $assessmentIds = $lessonPlanMapper->findForFolder($student->getSubjectCollection()->getIds(), $dateStart, $dateEnd, $publishDate, $student->getRole()->isStudent());
        // hydrate those assessments
        $this->visibleLessonPlans = $assessmentDataSource->findAllByIds($assessmentIds, ['order'  => ['due_date' => 'ASC']]);

        return \count($this->visibleLessonPlans) > 0;
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
                'returnUrlName'     => Assessment\Redirect::RETURN_URL,
                'encodedReturnUrl'  => Assessment\Redirect::encode('/'),
                'range'             => $this->range,
                'lessonPlans'       => $this->visibleLessonPlans,
            ]
        );
    }
}
