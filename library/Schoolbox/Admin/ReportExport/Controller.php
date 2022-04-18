<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
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
use Schoolbox\User;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Alaress\Database\Predicate;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends ConfigController
{

    private TermMapper $termMapper;

    private YearLevelMapper $yearLevelMapper;

    private CampusMapper $campusMapper;

    private WorkTypeMapper $workTypeMapper;

    private FolderMapper $folderMapper;

    private AssessmentMapperProxy $assessmentMapperProxy;

    private RubricDataSource $rubricDataSource;

    private Access $access;

    /**
     * @param TermMapper            $termMapper
     * @param YearLevelMapper       $yearLevelMapper
     * @param CampusMapper          $campusMapper
     * @param WorkTypeMapper        $workTypeMapper
     * @param FolderMapper          $folderMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param RubricDataSource      $rubricDataSource
     * @param Access                $access
     * @param ConfigMapper          $configMapper
     * @param FieldStrategyFactory  $fieldStrategyFactory
     */
    public function __construct(
        TermMapper $termMapper,
        YearLevelMapper $yearLevelMapper,
        CampusMapper $campusMapper,
        WorkTypeMapper $workTypeMapper,
        FolderMapper $folderMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        RubricDataSource $rubricDataSource,
        Access $access,
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory);
        $this->termMapper            = $termMapper;
        $this->yearLevelMapper       = $yearLevelMapper;
        $this->campusMapper          = $campusMapper;
        $this->workTypeMapper        = $workTypeMapper;
        $this->folderMapper          = $folderMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricDataSource      = $rubricDataSource;
        $this->access                = $access;
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


        $summaryPageOptions = [
            [
                'id'   => 'showOverallGrades',
                'name' => 'Show overall grades',
            ],
        ];
        $classPageOptions   = [
            [
                'id'   => 'showLearningLevels',
                'name' => 'Show learning levels',
            ],
            [
                'id'   => 'showCohortResults',
                'name' => 'Show cohort / class results',
            ],
            [
                'id'   => 'showCapabilityGrades',
                'name' => 'Show capability grades',
            ],
            [
                'id'   => 'showProgression',
                'name' => 'Show progression',
            ],
            [
                'id'   => 'showOverallGrades',
                'name' => 'Show overall grades',
            ],
        ];
        $assessmentOptions  = [
            [
                'id'   => 'showGrades',
                'name' => 'Show grades',
            ],
            [
                'id'   => 'showComments',
                'name' => 'Show comments',
            ],
            [
                'id'   => 'showRubrics',
                'name' => 'Show rubrics',
            ],
        ];

        $this->getResponse()->content->set(
            [
                'template' => '@adminReportExport/index.twig',
                'data'     => [
                    'pageCurrent'        => $this->router->generate('adminReportExport.getIndex'),
                    'allBreadcrumbs'     => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminReportExport.getIndex')),
                    'summaryPageOptions' => $summaryPageOptions,
                    'classPageOptions'   => $classPageOptions,
                    'assessmentOptions'  => $assessmentOptions,
                    'configContext'      => $configContext,
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
     * @api GET /adminv2/report-export/student/:studentId
     * @throws MapperException
     */
    public function getStudentReport(User $student)
    {
        $startDate = $this->request->query->get('startDate');
        $endDate   = $this->request->query->get('endDate');
        $viewAs    = $this->request->query->get('viewAs');
        // summary
        $showSummary             = (bool)$this->request->query->get('showSummary');
        $showSummaryOverallGrade = (bool)$this->request->query->get('showOverallGrade');
        $summaryWorkTypeIds      = $this->request->query->get('workTypes');
        $showSummaryGrades       = (bool)$this->request->query->get('showGrades');
        $showSummaryComments     = (bool)$this->request->query->get('showComments');
        $showSummaryRubrics      = (bool)$this->request->query->get('showRubrics');
        // class
        $showClass             = (bool)$this->request->query->get('showClass');
        $showClassOverallGrade = (bool)$this->request->query->get('showClassOverallGrade');
        $classWorkTypeIds      = $this->request->query->get('classWorkTypes');
        $showClassGrades       = (bool)$this->request->query->get('showClassGrades');
        $showClassComments     = (bool)$this->request->query->get('showClassComments');
        $showClassRubrics      = (bool)$this->request->query->get('showClassRubrics');


        // Dates
        try {
            $from = new \Alaress_Date_Format($startDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT);
            $to   = new \Alaress_Date_Format($endDate, \Alaress_Date_Format::DATABASE_DATE_FORMAT);
            // fake term
            $term = $this->termMapper->createModel()->setStart($from)->setActualFinish($to);
        } catch (\Exception $e) {
            $term = $this->termMapper->findForToday();
            $from = $term->getStart();
            $to   = $term->getActualFinish();
        }

        // View As
        switch ($viewAs) {
            case Role::STUDENT:
                $viewer = $student;
                break;
            case Role::PARENT:
                $student->loadParents();
                $viewer = $student->getParents()->first();
                break;
            case Role::STAFF:
            default:
                // the current admin
                $viewer = $this->getUser();
        }

        $summaryWorkTypes = !$summaryWorkTypeIds ? null : $this->workTypeMapper->findByColumn('id', array_map('intval', explode(',', $summaryWorkTypeIds)));
        $classWorkTypes   = !$classWorkTypeIds ? null : $this->workTypeMapper->findByColumn('id', array_map('intval', explode(',', $classWorkTypeIds)));

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

        $summaryAssessmentFeedbacks = $this->collateFeedbacks($summaryAssessments, $student, $viewer, $showSummaryRubrics);

        $classAssessmentFeedbacks = $this->collateFeedbacks($classAssessments, $student, $viewer, $showClassRubrics);

        $data = [
            'student'                    => $student,
            'folders'                    => $folders,
            'markbooks'                  => $markbooks,
            'summaryAssessments'         => $summaryAssessments,
            'classAssessments'           => $classAssessments,
            'summaryAssessmentFeedbacks' => $summaryAssessmentFeedbacks,
            'classAssessmentFeedbacks'   => $classAssessmentFeedbacks,
            'reportTitle'                => "Report {$from->getLocalShort()} ~ {$to->getLocalShort()}",
            'showSummary'                => $showSummary,
            'showSummaryOverallGrade'    => $showSummaryOverallGrade,
            'showSummaryGrades'          => $showSummaryGrades,
            'showSummaryComments'        => $showSummaryComments,
            'showSummaryRubrics'         => $showSummaryRubrics,
            'showClass'                  => $showClass,
            'showClassOverallGrade'      => $showClassOverallGrade,
            'showClassGrades'            => $showClassGrades,
            'showClassComments'          => $showClassComments,
            'showClassRubrics'           => $showClassRubrics,
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
                $feedback    = $participant->findMostRecentInstructorGivenPublishedFeedback();
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
                    ->equals('lct.type', 'Report Export')
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_SETTINGS);
    }
}
