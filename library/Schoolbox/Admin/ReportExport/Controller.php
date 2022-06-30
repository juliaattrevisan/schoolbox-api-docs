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
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserBulkMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Alaress\Database\Predicate;
use Schoolbox\Admin\ReportExport\Parameters as ReportParams;
use Scrub;

/**
 * Class Controller
 *
 * Admin report export page
 *
 * @package Schoolbox\Admin\ReportExport
 */
class Controller extends ConfigController
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    private const FILENAME_MAX_LENGTH = 250;


    /**
     * This file contains an html fragment that represents the footer
     * for all the generated summative reports. Schools wanting a custom
     * theme or layout for their reports will require customisation of almost
     * everything inside the templates folder
     */
    private const FOOTER_HTML = '/templates/footer.html';


    private Mapper $reportMapper;

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

    private RoleMapper $roleMapper;

    /**
     * Constructor
     *
     * @param Mapper                $reportMapper
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
     * @param RoleMapper            $roleMapper
     * @param ConfigMapper          $configMapper
     * @param FieldStrategyFactory  $fieldStrategyFactory
     * @param Rebuilder             $rebuilder
     */
    public function __construct(
        Mapper $reportMapper,
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
        RoleMapper $roleMapper,
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder);
        $this->reportMapper          = $reportMapper;
        $this->userMapper            = $userMapper;
        $this->termMapper            = $termMapper;
        $this->yearLevelMapper       = $yearLevelMapper;
        $this->campusMapper          = $campusMapper;
        $this->workTypeMapper        = $workTypeMapper;
        $this->folderMapper          = $folderMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->rubricDataSource      = $rubricDataSource;
        $this->access                = $access;
        $this->userBulkMapper        = $userBulkMapper;
        $this->roleMapper            = $roleMapper;
    }


    /**
     * A list of summative reports
     *
     * @return void
     * @throws RouteNotFound
     * @api GET /adminv2/report-export
     */
    public function getList(): void
    {
        /* Get a list of all the available reports,
         * ordered alphabetically ascending
         */
        $reports = $this->reportMapper->findAll([
            'order' => [
                'title' => 'ASC'
            ]
        ]);

        $this->getResponse()->content->set([
            'template' => '@adminReportExport/list.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminReportExport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminReportExport.getList')),
                'reports' => $reports
            ]
        ]);
    }


    /**
     * The form you use to create a report
     *
     * @return void
     * @throws RouteNotFound
     * @api GET /adminv2/report-export/create
     */
    public function getCreate(): void
    {
        /* Logo image
         */
        $logoZone = (new Zone('logo'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a logo image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*')
            ->setFilesystemContext('images');

        $this->getResponse()->content->set([
            'template' => '@adminReportExport/create.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminReportExport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminReportExport.getList')),
                'report' => null,
                'logoZone' => $logoZone->getTwigContext()
            ]
        ]);
    }


    /**
     * Create a new summative report
     *
     * @return void
     * @throws MapperException
     * @throws \DebugBar\DebugBarException
     * @api POST /report-export/create
     */
    public function postCreate(): void
    {
        $report = $this->reportMapper->createModel();

        $this->postUpdate($report);
    }


    /**
     * The form you use to update a report
     *
     * @param Model $report
     * @return void
     * @throws RouteNotFound
     * @api GET /adminv2/report-export/{reportId}/update
     */
    public function getUpdate(Model $report): void
    {
        /* Logo image
         */
        $logoZone = (new Zone('logo'))
            ->setMulti(false)
            ->setAttachPrompt('Attach a logo image')
            ->setAttachmentsLabel('')
            ->setMimeFilter('image/*')
            ->setFilesystemContext('images');

        if ($report->getLogo()) {
            $logoZone->attachFiles([$report->getLogo()]);
        }

        $this->getResponse()->content->set([
            'template' => '@adminReportExport/create.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminReportExport.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminReportExport.getList')),
                    [['Modify', null]]
                ),
                'report' => $report,
                'options' => $this->decompilePayload($report),
                'logoZone' => $logoZone->getTwigContext()
            ]
        ]);
    }


    /**
     * Persist the report's contents to the db
     *
     * @param Model $report
     * @return void
     * @throws \DebugBar\DebugBarException
     * @throws \JsonException
     * @api POST /report-export/{reportId}/update
     */
    public function postUpdate(Model $report): void
    {
        $postData = $this->getRequest()->post->getArrayCopy();

        /* This is the json-encoded payload we persist in the db
         * To avoid making millions of schema we can just serialize it
         */
        $payloadKeys = [
            'terms' => true,
            'yearLevels' => true,
            'campuses' => true,
            'students' => true,
            'summary' => true,
            'class' => true,
        ];

        $options = json_encode(array_intersect_key($postData, $payloadKeys), JSON_THROW_ON_ERROR);

        /* Cram in all the data
         */
        $report->setTitle($postData['title'])
            ->setIntroduction($postData['introduction'])
            ->setOptions($options)
            ->setUpdatedBy($this->getUser());

        /* If any Logo was provided, remember it
         * Otherwise clear it
         */
        if (\is_array($postData['attachment'] ?? null)) {
            /** @var \Schoolbox\Resource\Storage\Model $logo */
            $logo = (new CollectorFactory())->build($postData['attachment'] ?: [])->getFirst();
            $report->setLogo($logo);
        } else {
            $report->setLogo(null);
        }

        $this->reportMapper->save($report);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminReportExport.getList'));
    }


    /**
     * Break open the options payload
     * because selectizes need pieces of this
     *
     * @param Model $report
     * @return array
     */
    public function decompilePayload(Model $report): array
    {
        $options = [];
        $optionPayload = json_decode($report->getOptions(), true);

        /* Extract the optional summary heading string
         * Extract the checkbox settings
         */
        $options['summary'] = $optionPayload['summary'];

        /* Extract the optional class checkboxes
         * Extract the checkbox settings
         */
        $options['class'] = $optionPayload['class'];

        /* Squish out the Terms
         */
        $terms = $this->termMapper->findByColumn('id', explode(',', $optionPayload['terms']));

        foreach ($terms as $term) {
            $options['terms'][] = [
                'id'   => $term->getId(),
                'name' => $term->getTitle(),
            ];
        }

        /* Squish out the Year Levels
         */
        $yearLevels = $this->yearLevelMapper->findByColumn('id', explode(',', $optionPayload['yearLevels']));

        foreach ($yearLevels as $yearLevel) {
            $options['yearLevels'][] = [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        }

        /* Squish out the Campuses
         */
        $campuses = $this->campusMapper->findByColumn('id', explode(',', $optionPayload['campuses']));

        foreach ($campuses as $campus) {
            $options['campuses'][] = [
                'id'   => $campus->getId(),
                'name' => $campus->getName(),
            ];
        }

        /* Squish out the Students
         */
        $students = $this->userMapper->findByColumn('id', explode(',', $optionPayload['students']));

        foreach ($students as $student) {
            $options['students'][] = [
                'id'   => $student->getId(),
                'name' => $student->getFullName(),
            ];
        }

        /* Summary Work Types
         */
        $summaryWorkTypes = $this->workTypeMapper->findByColumn('id', $optionPayload['summary']['workType']);

        foreach ($summaryWorkTypes as $summaryWorkType) {
            $options['summary']['workType'][] = [
                'id'   => $summaryWorkType->getId(),
                'name' => $summaryWorkType->getName(),
            ];
        }

        /* Class Work Types
         */
        $classWorkTypes = $this->workTypeMapper->findByColumn('id', $optionPayload['class']['workType']);

        foreach ($classWorkTypes as $classWorkType) {
            $options['class']['workType'][] = [
                'id'   => $classWorkType->getId(),
                'name' => $classWorkType->getName(),
            ];
        }

        return $options;
    }


    /**
     * Get terms (academic periods) for selectize
     *
     * @return void
     * @api GET /adminv2/report-export/terms
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
     *
     * @return void
     * @api GET /adminv2/report-export/yearlevels
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
     * Get work types for selectize
     * @return void
     * @api GET /adminv2/report-export/worktypes
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
     * Get campuses for selectize
     *
     * @return void
     * @api GET /adminv2/report-export/campuses
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
        // Hack a maxlength into the report title setting
        if (is_iterable($configContext['settings'] ?? null)) {
            foreach ($configContext['settings'] as $i => $setting) {
                $name = $setting['data']['name'] ?? null;
                if ($name === ConfigMapper::NAME_REPORT_TITLE) {
                    $configContext['settings'][$i]['data']['maxlength'] = self::FILENAME_MAX_LENGTH;
                    break;
                }
            }
        }
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
            /* Check the config to see what Role Type should have come out in the zip
             * This lets you prevent staff users from getting a pdf when you only wanted students
             */
            $roleIds = $this->roleMapper->findByColumn('group_type', Role::STUDENT)->indexBy('id')->keys();

            $students   = $this->userBulkMapper->bulkModifySearch(
                empty($reportParams->getCampuses()) ? [UserBulkMapper::ANY] : $reportParams->getCampuses(),
                $roleIds,
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

        $params  = $reportParams->getParams();
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
        $escapedTitle = Scrub::filename(trim($this->getConfigLoader()->get(ConfigMapper::NAME_REPORT_TITLE) ?? ''));

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->getDi()->get('taskd.downloadPagesMessageFactory');
        $reportCount    = 0;

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
                    $externalId = Scrub::filename($student->getExternalId() ?: 'unknown');
                    $fullName   = Scrub::filename($student->getFullName());
                    $filename   = $escapedTitle !== ''
                        ? sprintf('%s - %s', $fullName, $escapedTitle)
                        : $fullName;
                    $messageFactory = $messageFactory->addPdfContent(
                        sprintf(
                            "%s/%s.pdf",
                            $externalId,
                            mb_substr($filename, 0, self::FILENAME_MAX_LENGTH, 'UTF-8')
                        ),
                        $urlBase->setPath($reportPath)->setQuery($params)->toString(),
                    );
                    $reportCount++;
                }
            } catch (RouteNotFound $e) {
                // noop
            }
        }

        if ($reportCount > 0) {
            // Get the taskd context, and send
            /** @var Producer $producer */
            $producer = $this->getDi()->get('taskd.messageProducer');

            $userSerializer = new ModelToArray();

            $date = \Alaress_Date_Format::now()->getDatabaseDate();
            $outfile = $escapedTitle !== ''
                ? ($date . '-' . $escapedTitle)
                : $date;

            /* This $message instance is the payload that we send to taskd
             * which it then unpacks, configures its Gotenberg client, and uses
             * to generate the pdfs. This is the bridge between the systems
             */
            $message = $messageFactory
                ->setUser($this->getUser())
                ->setCallbackUrl(
                    $urlBase
                        ->setPath(
                            $this->getRouter()->generate(
                                'apiusernotify.apiUserNotify',
                                ['userId' => $this->getUser()->getId()]
                            )
                        )
                        ->setQuery(null)
                        ->toString()
                )
                ->setCallbackParams([
                    'message' => sprintf(
                        'Your summative report %s is ready to download',
                        $this->getConfigLoader()->get(ConfigMapper::NAME_REPORT_TITLE),
                    ),
                    'from'    => call_user_func($userSerializer, $this->getUser()),
                    'to'      => call_user_func($userSerializer, $this->getUser()),
                ])
                ->setPdfFooter(file_get_contents(__DIR__ . self::FOOTER_HTML))
                ->build(mb_substr($outfile, 0, self::FILENAME_MAX_LENGTH, 'UTF-8') . '.zip');
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
                    'reportCount'    => $reportCount,
                    'warnings'       => $warnings,
                ],
            ]
        );
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
            'summaryHeading'             => $params->getSummaryHeading(),
            'showSummaryOverallGrade'    => $params->getShowSummaryOverallGrade(),
            'showSummaryGrades'          => $params->getShowSummaryGrades(),
            'showSummaryComments'        => $params->getShowSummaryComments(),
            'showSummaryRubrics'         => $params->getShowSummaryRubrics(),
            'showClass'                  => $params->getShowClass(),
            'showClassOverallGrade'      => $params->getShowClassOverallGrade(),
            'showClassGrades'            => $params->getShowClassGrades(),
            'showClassComments'          => $params->getShowClassComments(),
            'showClassRubrics'           => $params->getShowClassRubrics(),

            /* Extract any custom summative report styles from Admin Skin Config
             * and slam them in here so schools can tweak our designs
             */
            'customReportStyle'          => (new CustomStyleExtractor())->extract((string)$this->getConfigLoader()->get('skin_custom_css'))
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
