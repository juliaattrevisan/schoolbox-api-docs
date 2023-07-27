<?php

namespace Schoolbox\Learning\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Assessment\IAssessable;

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
        $student = $this->getUser();

        /**
         * If parent role, or user has no subjects
         * exit early
         */
        if ($student->getRole()->isParent()) {
            return false;
        }

        $lessonPlanMapper = LessonPlanMapper::getInstance();
        $assessmentDataSource = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $this->range = is_numeric($this->getConfig('range')) ? $this->getConfig('range') : self::RANGE_DEFAULT;

        $dateStart = \Alaress_Date_Format::now()->startOfDay()->addDays(-$this->range);
        $dateEnd = \Alaress_Date_Format::now()->endOfDay()->addDays($this->range);

        // grab the assessmentID for all lesson plans from the students subject list
        $assessmentIds = $lessonPlanMapper->findForFolder([$this->getContext()->getFolder()->getId()], $dateStart, $dateEnd, $student->getRole()->isStudent());
        // hydrate those assessments
        $this->visibleLessonPlans = $assessmentDataSource->findAllByIds($assessmentIds, ['order'  => ['due_date' => 'DESC']]);

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
