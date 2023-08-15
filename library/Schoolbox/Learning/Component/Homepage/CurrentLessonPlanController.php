<?php

namespace Schoolbox\Learning\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;

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
     * Does the user have edit access to this component / page
     * @var bool
     */
    private bool $readOnly;

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
        $this->readOnly = $this->getContentPermission() < UserAccess::ACCESS_WRITE;
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
        $publishDate = \Alaress_Date_Format::now();

        // grab the assessmentID for all lesson plans from the students subject list
        $assessmentIds = $lessonPlanMapper->findForFolder([$this->getContext()->getFolder()->getId()], $dateStart, $dateEnd, $publishDate, $student->getRole()->isStudent());
        // hack to warm the db cache for courses, so when hydrating the assessments we actually get the course folder details (name)
        $courses = CourseImplementationMapper::GetInstance()->findByFolderId($this->getContext()->getFolder()->getId());
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
                'component'         => $this,
                'range'             => $this->range,
                'lessonPlans'       => $this->visibleLessonPlans,
                'returnUrlName'     => Assessment\Redirect::RETURN_URL,
                'encodedReturnUrl'  => Assessment\Redirect::encode('/homepage/' . $this->getContext()->getFolder()->getId()),
                'readOnly'          => $this->readOnly,
            ]
        );
    }
}
