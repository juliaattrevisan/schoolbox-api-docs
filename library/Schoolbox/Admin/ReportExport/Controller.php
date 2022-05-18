<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\Settings\ModuleConfig;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Grades\Helper;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserBulkMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Alaress\Database\Predicate;
use Schoolbox\Admin\ReportExport\Parameters as ReportParams;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends ConfigController
{

    private UserMapper $userMapper;

    private TermMapper $termMapper;

    private YearLevelMapper $yearLevelMapper;

    private CampusMapper $campusMapper;

    private WorkTypeMapper $workTypeMapper;

    private FolderMapper $folderMapper;

    private AssessmentMapperProxy $assessmentMapperProxy;

    private RubricDataSource $rubricDataSource;

    private Access $access;

    private UserBulkMapper $userBulkMapper;

    private Jwt $tokenBuilder;

    /**
     * Constructor
     *
     * @param UserMapper            $userMapper
     * @param TermMapper            $termMapper
     * @param YearLevelMapper       $yearLevelMapper
     * @param CampusMapper          $campusMapper
     * @param WorkTypeMapper        $workTypeMapper
     * @param FolderMapper          $folderMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param RubricDataSource      $rubricDataSource
     * @param Access                $access
     * @param UserBulkMapper        $userBulkMapper
     * @param Jwt                   $tokenBuilder
     * @param ConfigMapper          $configMapper
     * @param FieldStrategyFactory  $fieldStrategyFactory
     * @param Rebuilder             $rebuilder
     */
    public function __construct(
        UserMapper $userMapper,
        TermMapper $termMapper,
        YearLevelMapper $yearLevelMapper,
        CampusMapper $campusMapper,
        WorkTypeMapper $workTypeMapper,
        FolderMapper $folderMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        RubricDataSource $rubricDataSource,
        Access $access,
        UserBulkMapper $userBulkMapper,
        Jwt $tokenBuilder,
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder);
        $this->userMapper            = $userMapper;
        $this->termMapper            = $termMapper;
        $this->yearLevelMapper       = $yearLevelMapper;
        $this->campusMapper          = $campusMapper;
        $this->workTypeMapper        = $workTypeMapper;
        $this->folderMapper          = $folderMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricDataSource      = $rubricDataSource;
        $this->tokenBuilder          = $tokenBuilder;
        $this->access                = $access;
        $this->userBulkMapper        = $userBulkMapper;
    }

    /**
     * Landing page
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @api GET /adminv2/report-export
     */
    public function getIndex(): void
    {
        $configContext = $this->buildConfigContext(
            $this->buildConfigPredicate(),
            $this->router->generate('adminReportExport.postConfig'),
            [ModuleConfig::CONTEXT_USE_BOTTOM_DRAWER => false,],
        );
        $this->getResponse()->content->set(
            [
                'template' => '@adminReportExport/index.twig',
                'data'     => [
                    'pageCurrent'              => $this->router->generate('adminReportExport.getIndex'),
                    'allBreadcrumbs'           => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminReportExport.getIndex')),
                    'summaryPageOptions'       => ReportParams::SUMMARY_PAGE_OPTIONS,
                    'classPageOptions'         => ReportParams::CLASS_PAGE_OPTIONS,
                    'summaryAssessmentOptions' => ReportParams::SUMMARY_ASSESSMENT_OPTIONS,
                    'classAssessmentOptions'   => ReportParams::CLASS_ASSESSMENT_OPTIONS,
                    'configContext'            => $configContext,
                ],
            ]
        );
    }

    /**
     * Save settings
     *
     * @throws RouteNotFound
     * @throws MapperException
     */
    public function postConfig(): void
    {
        $this->updateConfig($this->request->post->get(), $this->buildConfigPredicate());
        $this->response->redirect->afterPost($this->router->generate('adminReportExport.getIndex'));
    }

    /**
     * Request report generation to taskd
     * @return void
     * @throws RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function postReport(): void
    {
        $reportParams = new ReportParams($this->request->post->getArrayCopy(), $this->termMapper);

        // find students
        if (empty($reportParams->getCampuses()) && empty($reportParams->getYearLevels())) {
            // if both campuses and year levels are not provided, generate only for specific students
            $studentIds = [];
        } else {
            $students   = $this->userBulkMapper->bulkModifySearch(
                empty($reportParams->getCampuses()) ? [UserBulkMapper::ANY] : $reportParams->getCampuses(),
                [UserBulkMapper::ANY], // TODO: limit to students?
                UserBulkMapper::ANY,
                empty($reportParams->getYearLevels()) ? [UserBulkMapper::ANY] : $reportParams->getYearLevels(),
                UserBulkMapper::ANY
            );
            $studentIds = array_column($students, 'id');
        }

        if ($reportParams->getStudentIds()) {
            $studentIds = array_merge($studentIds, $reportParams->getStudentIds());
            $studentIds = array_unique($studentIds);
        }
        $reportStudents = $this->userMapper->findByColumn('id', $studentIds);

        $warnings = [];

        $reports = [];
        $params  = $reportParams->getParams();
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
        $escapedTitle = preg_replace(
            '/[^a-zA-Z\d\-_]/',
            '_',
            trim($this->getConfigLoader()->get(ConfigMapper::NAME_REPORT_TITLE))
        );
        foreach ($reportStudents as $student) {
            if ($reportParams->getViewAs() === Role::PARENT) {
                $student->loadParents();
                if ($student->getParents()->count() === 0) {
                    $warnings[] = sprintf('Unable to generate report for student id %d. No parents.', $student->getId());
                    continue;
                }
            }
            try {
                $reportPath = $this->router->generate('adminReportExport.getStudentReport', [
                    'studentId' => $student->getId(),
                    'student'   => $student,
                ]);
                if ($reportPath) {
                    $reports[] = [
                        'filename' => sprintf(
                            "%s/%s - %s.pdf",
                            ($student->getExternalId() ?: 'unknown'),
                            $student->getFullName(),
                            $escapedTitle,
                        ),
                        'urls'     => [$urlBase->setPath($reportPath)->setQuery($params)->toString()],
                    ];
                }
            } catch (RouteNotFound $e) {
                // noop
            }
        }

        if (!empty($reports)) {
            // Get the taskd context, and send
            /** @var Producer $producer */
            $producer = $this->getDi()->get('taskd.messageProducer');

            /** @var MessageFactory $messageFactory */
            $messageFactory = $this->getDi()->get('taskd.messageFactory');
            $userSerializer = new ModelToArray();

            $message = $messageFactory->build(
                'download-pages',
                [
                    'token'     => $this->tokenBuilder->generate($this->getUser()),
                    'content'   => $reports,
                    'outfile'   => \Alaress_Date_Format::now()->getDatabaseDate() . '-' . $escapedTitle . '.zip',
                    'gotenberg' => [
                        'url' => $this->getConfigLoader()->get(ConfigMapper::NAME_GOTENBERG_HOST),
                        'key' => $this->getConfigLoader()->get(ConfigMapper::NAME_GOTENBERG_API_KEY),
                    ],
                ],
                $urlBase->setPath($this->getRouter()->generate('apiusernotify.apiUserNotify', ['userId' => $this->getUser()->getId()]))
                        ->setQuery(null)
                        ->toString(),
                [
                    'message' => sprintf('Your summative report %s is ready to download', $this->getConfigLoader()->get(ConfigMapper::NAME_REPORT_TITLE)),
                    'from'    => call_user_func($userSerializer, $this->getUser()),
                    'to'      => call_user_func($userSerializer, $this->getUser()),
                ],
                $this->getUser(),
            );

            $producer->send($message);
        }
        $this->getResponse()->content->set(
            [
                'template' => '@adminReportExport/result.twig',
                'data'     => [
                    'pageCurrent'    => $this->router->generate('adminReportExport.getIndex'),
                    'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs(
                        $this->router->generate('adminReportExport.getIndex')
                    ),
                    'reportCount'    => count($reports),
                    'warnings'       => $warnings,
                ],
            ]
        );
    }

    /**
     * Get terms for selectize
     * @return void
     * @api GET /adminv2/report-export/getTerms
     */
    public function getTerms(): void
    {
        $output = [];
        $terms  = $this->termMapper->findAll();
        foreach ($terms as $term) {
            $output[] = [
                'id'   => $term->getId(),
                'name' => $term->getTitle(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Get year levels for selectize
     * @return void
     * @api GET /adminv2/report-export/getYearLevels
     */
    public function getYearLevels(): void
    {
        $output     = [['id' => 0, 'name' => 'No Year Level']];
        $yearLevels = $this->yearLevelMapper->findAll()->sortBy('sequence', 'ASC');
        foreach ($yearLevels as $yearLevel) {
            $output[] = [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Get campuses for selectize
     * @return void
     * @api GET /adminv2/report-export/getCampuses
     */
    public function getCampuses(): void
    {
        $output   = [];
        $campuses = $this->campusMapper->findAll()->sortBy('sequence', 'ASC');
        foreach ($campuses as $campus) {
            $output[] = [
                'id'   => $campus->getId(),
                'name' => $campus->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Get work types for selectize
     * @return void
     * @api GET /adminv2/report-export/getWorkTypes
     */
    public function getWorkTypes(): void
    {
        $output    = [];
        $workTypes = $this->workTypeMapper->findAll()->sortBy('sequence', 'ASC');
        foreach ($workTypes as $workType) {
            $output[] = [
                'id'   => $workType->getId(),
                'name' => $workType->getName(),
            ];
        }
        $this->getResponse()->content->set($this->buildJsonOutput($output));
    }

    /**
     * Generates report
     * https://vagrant.dev.schoolbox.cloud/adminv2/report-export/student/25?startDate=2021-01-01&endDate=2022-12-31&showOverallGrade=1&showSummary=1
     * @param User $student
     * @return void
     * @throws MapperException
     * @api GET /adminv2/report-export/student/:studentId
     */
    public function getStudentReport(User $student)
    {
        $params = new ReportParams($this->request->query->getArrayCopy(), $this->termMapper);

        // Dates
        try {
            $from = new \Alaress_Date_Format($params->getStartDate(), \Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $to   = new \Alaress_Date_Format($params->getEndDate(), \Alaress_Date_Format::DATABASE_DATE_FORMAT);
            // fake term
            $term = $this->termMapper->createModel()->setStart($from)->setActualFinish($to);
        } catch (\Exception $e) {
            $term = $this->termMapper->findForToday();
            $from = $term->getStart();
            $to   = $term->getActualFinish();
        }

        // View As
        switch ($params->getViewAs()) {
            case Role::STUDENT:
                $viewer = $student;
                break;
            case Role::PARENT:
                $student->loadParents();
                $viewer = $student->getParents()->first();
                if (!$viewer) {
                    $this->sendError(sprintf('Unable to generate report for student id %d. No parents.', $student->getId()));
                }
                break;
            case Role::STAFF:
            default:
                // the current admin
                $viewer = $this->getUser();
        }

        $summaryWorkTypes = !$params->getSummaryWorkTypeIds() ? null : $this->workTypeMapper->findByColumn('id', $params->getSummaryWorkTypeIds());
        $classWorkTypes   = !$params->getClassWorkTypeIds() ? null : $this->workTypeMapper->findByColumn('id', $params->getClassWorkTypeIds());

        $markSchemeFactory = new Factory($this->getConfigLoader());

        $gradeHelper = new Helper(
            $this->folderMapper,
            $this->assessmentMapperProxy,
            $this->access,
            $markSchemeFactory
        );

        [
            $folders,
            $markbooks,
            $assessments,
        ] = $gradeHelper->generateGrades($viewer, $student, $term, $from, $to);

        $summaryAssessments = new Collection();
        if ($summaryWorkTypes && $summaryWorkTypes->count() > 0) {
            $summaryAssessments = $gradeHelper->filterAssessments($viewer, $student, $assessments, $summaryWorkTypes);
        }
        $classAssessments = new Collection();
        if ($classWorkTypes && $classWorkTypes->count() > 0) {
            $classAssessments = $gradeHelper->filterAssessments($viewer, $student, $assessments, $classWorkTypes);
        }

        $summaryAssessmentFeedbacks = $this->collateFeedbacks($summaryAssessments, $student, $viewer, $params->getShowSummaryRubrics());

        $classAssessmentFeedbacks = $this->collateFeedbacks($classAssessments, $student, $viewer, $params->getShowClassRubrics());

        $data = [
            'student'                    => $student,
            'folders'                    => $folders,
            'markbooks'                  => $markbooks,
            'summaryAssessments'         => $summaryAssessments,
            'classAssessments'           => $classAssessments,
            'summaryAssessmentFeedbacks' => $summaryAssessmentFeedbacks,
            'classAssessmentFeedbacks'   => $classAssessmentFeedbacks,
            'reportTitle'                => "Report {$from->getLocalShort()} ~ {$to->getLocalShort()}",
            'showSummary'                => $params->getShowSummary(),
            'showSummaryOverallGrade'    => $params->getShowSummaryOverallGrade(),
            'showSummaryGrades'          => $params->getShowSummaryGrades(),
            'showSummaryComments'        => $params->getShowSummaryComments(),
            'showSummaryRubrics'         => $params->getShowSummaryRubrics(),
            'showClass'                  => $params->getShowClass(),
            'showClassOverallGrade'      => $params->getShowClassOverallGrade(),
            'showClassGrades'            => $params->getShowClassGrades(),
            'showClassComments'          => $params->getShowClassComments(),
            'showClassRubrics'           => $params->getShowClassRubrics(),
        ];
        $this->getResponse()->content->set(
            $this->buildUnthemedHtmlOutput('@adminReportExport/studentReport.twig', $data)
        );
    }

    /**
     * Hydrate feedback and returns as a collection indexed by assessment id
     * @param Collection $folderAssessments
     * @param User       $student
     * @param User       $viewer
     * @param bool       $includeRubric
     * @return Collection
     */
    public function collateFeedbacks(Collection $folderAssessments, User $student, User $viewer, bool $includeRubric): Collection
    {
        $feedbacks = new Collection();
        foreach ($folderAssessments as $folderId => $assessments) {
            /** @var IAssessable $assessment */
            foreach ($assessments as $assessment) {
                if (!$assessment->isFeedbackVisibleToUser($viewer)) {
                    $feedbacks->offsetSet($assessment->getId(), null);
                    continue;
                }
                $participant = $assessment->getParticipantIfExtant($student);
                if (!$participant) {
                    continue;
                }
                $feedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
                if (
                    $includeRubric
                    && $assessment instanceof DueWork
                    && $feedback instanceof DueWorkFeedback
                    && $assessment->getUseRubric()
                ) {
                    try {
                        $rubric = $this->rubricDataSource->retrieveMarkedGrid($feedback);
                    } catch (MapperException $e) {
                        continue;
                    }
                    $feedback->setRubricMarkedGrid($rubric);
                }
                $feedbacks->offsetSet($assessment->getId(), $feedback);
            }
        }
        return $feedbacks;
    }

    /**
     * Returns a predicate suitable for finding config for this module
     *
     * @return Predicate\Builder
     */
    private function buildConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_REPORT_EXPORT);
    }
}
