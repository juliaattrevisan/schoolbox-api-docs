<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\SummativeReport;

use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Application\Exception\Exception;
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

/**
 * Class Controller
 *
 * Admin Summative Report page
 *
 * @package Schoolbox\Admin\SummativeReport
 */
class Controller extends ConfigController
{
    /**
     * This file contains an html fragment that represents the footer
     * for all the generated summative reports. Schools wanting a custom
     * theme or layout for their reports will require customisation of almost
     * everything inside the templates folder
     */
    private const FOOTER_HTML = '/templates/footer.html';


    /**
     * These are used in the View As dropdown, the existence of
     * which in and of itself is questionable atm
     */
    private const AVAILABLE_ROLETYPES = [
        Role::STUDENT => 'Student',
        Role::PARENT => 'Parent',
        Role::STAFF => 'Staff',
    ];


    /**
     * This id corresponds to a fictitious option "No Year Level"
     * which doesn't exist in a lookup
     */
    private const NO_YEAR_LEVEL_ID = 0;


    /**
     * This tuple corresponds to a fictitious option "No Year Level"
     * which doesn't exist in a lookup
     */
    private const NO_YEAR_LEVEL_TUPLE = [
        'id' => self::NO_YEAR_LEVEL_ID, 'name' => 'No Year Level'
    ];


    /**
     * @var \Schoolbox\Error\Mapper
     */
    private \Schoolbox\Error\Mapper $errorMapper;

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
     * @param \Schoolbox\Error\Mapper $errorMapper
     * @param Mapper                  $reportMapper
     * @param UserMapper              $userMapper
     * @param TermMapper              $termMapper
     * @param YearLevelMapper         $yearLevelMapper
     * @param CampusMapper            $campusMapper
     * @param WorkTypeMapper          $workTypeMapper
     * @param FolderMapper            $folderMapper
     * @param AssessmentMapperProxy   $assessmentMapperProxy
     * @param RubricDataSource        $rubricDataSource
     * @param Access                  $access
     * @param UserBulkMapper          $userBulkMapper
     * @param RoleMapper              $roleMapper
     * @param ConfigMapper            $configMapper
     * @param FieldStrategyFactory    $fieldStrategyFactory
     * @param Rebuilder               $rebuilder
     */
    public function __construct(
        \Schoolbox\Error\Mapper $errorMapper,
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
        $this->errorMapper           = $errorMapper;
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
     * @api GET /adminv2/summative-report
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
            'template' => '@adminSummativeReport/list.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminSummativeReport.getList')),
                'reports' => $reports
            ]
        ]);
    }


    /**
     * The form you use to create a report
     *
     * @return void
     * @throws RouteNotFound
     * @api GET /adminv2/summative-report/create
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
            'template' => '@adminSummativeReport/create.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminSummativeReport.getList')),
                'report' => null,
                'options' => Model::DEFAULT_OPTIONS,
                'viewAs' => self::AVAILABLE_ROLETYPES,
                'logoZone' => $logoZone->getTwigContext()
            ]
        ]);
    }


    /**
     * Create a new summative report
     *
     * @return void
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @api POST /summative-report/create
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
     * @throws \DebugBar\DebugBarException
     * @throws \JsonException
     * @api GET /adminv2/summative-report/{reportId}/update
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
            'template' => '@adminSummativeReport/create.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminSummativeReport.getList')),
                    [['Modify', null]]
                ),
                'report' => $report,
                'options' => $this->decompilePayload($report),
                'viewAs' => self::AVAILABLE_ROLETYPES,
                'logoZone' => $logoZone->getTwigContext()
            ]
        ]);
    }


    /**
     * Persist the report's contents to the db
     *
     * @param Model $report
     * @return void
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @api POST /summative-report/{reportId}/update
     */
    public function postUpdate(Model $report): void
    {
        $postData = $this->getRequest()->post->getArrayCopy();

        /* This is the json-encoded payload we persist in the db
         * To avoid making millions of schema we can just serialize it
         */
        $payloadKeys = [
            'viewAs' => true,
            'terms' => true,
            'yearLevels' => true,
            'campuses' => true,
            'students' => true,
            'summary' => true,
            'class' => true,
        ];

        $options = json_encode(array_intersect_key($postData, $payloadKeys), JSON_THROW_ON_ERROR);

        /* Title is required
         */
        if ('' === trim((string)$postData['title'])) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        /* Academic Periods are required
         */
        if ('' === trim((string)$postData['terms'])) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

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

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminSummativeReport.getList'));
    }


    /**
     * Break open the options payload
     * because selectizes need pieces of this
     *
     * @param Model $report
     * @return array
     * @throws \DebugBar\DebugBarException
     * @throws \JsonException
     */
    public function decompilePayload(Model $report): array
    {
        /* Reconstruct a selectize-compatible set of tuples into $options
         * which starts out as a clone fo $optionsPayload
         */
        $options = $optionsPayload = $report->decode();

        /* Squish out the Terms
         */
        $terms = $this->termMapper->findByColumn('id', $optionsPayload['terms']);

        $options['terms'] = [];

        foreach ($terms as $term) {
            $options['terms'][] = [
                'id'   => $term->getId(),
                'name' => $term->getTitle(),
            ];
        }

        /* Squish out the Year Levels
         */
        $yearLevels = $this->yearLevelMapper->findByColumn('id', $optionsPayload['yearLevels']);

        $options['yearLevels'] = [];

        /* Arbitrarily-injected "No Year Level" option if it was in the payload originally
         */
        if (\in_array(self::NO_YEAR_LEVEL_ID, $optionsPayload['yearLevels'], false)) {
            $options['yearLevels'] = [
                self::NO_YEAR_LEVEL_TUPLE
            ];
        }

        /* Cram in the rest
         */
        foreach ($yearLevels as $yearLevel) {
            $options['yearLevels'][] = [
                'id'   => $yearLevel->getId(),
                'name' => $yearLevel->getName(),
            ];
        }

        /* Squish out the Campuses
         */
        $campuses = $this->campusMapper->findByColumn('id', $optionsPayload['campuses']);

        $options['campuses'] = [];

        foreach ($campuses as $campus) {
            $options['campuses'][] = [
                'id'   => $campus->getId(),
                'name' => $campus->getName(),
            ];
        }

        /* Squish out the Students
         */
        $students = $this->userMapper->findByColumn('id', $optionsPayload['students']);

        $options['students'] = [];

        foreach ($students as $student) {
            $options['students'][] = [
                'id'   => $student->getId(),
                'name' => $student->getFullName(),
            ];
        }

        /* Summary Work Types
         */
        $summaryWorkTypes = $this->workTypeMapper->findByColumn('id', $optionsPayload['summary']['workType'] ?? []);

        $options['summary']['workType'] = [];

        foreach ($summaryWorkTypes as $summaryWorkType) {
            $options['summary']['workType'][] = [
                'id'   => $summaryWorkType->getId(),
                'name' => $summaryWorkType->getName(),
            ];
        }

        /* Class Work Types
         */
        $classWorkTypes = $this->workTypeMapper->findByColumn('id', $optionsPayload['class']['workType'] ?? []);

        $options['class']['workType'] = [];

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
     * @api GET /adminv2/summative-report/terms
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
     * @api GET /adminv2/summative-report/yearlevels
     */
    public function getYearLevels(): void
    {
        $output = [self::NO_YEAR_LEVEL_TUPLE];

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
     * @api GET /adminv2/summative-report/worktypes
     */
    public function getWorkTypes(): void
    {
        $output = [];
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
     * @api GET /adminv2/summative-report/campuses
     */
    public function getCampuses(): void
    {
        $output = [];
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
     * Duplicate a report's settings
     *
     * @param Model $report
     * @return void
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @api GET /adminv2/summative-report/{reportId}/duplicate
     */
    public function getDuplicate(Model $report): void
    {
        $newTitle = sprintf(
            '%s (copy)',
            $report->getTitle()
        );

        /* Remove id and shank in a new title
         */
        $report->setId(null)
            ->setTitle($newTitle)
            ->setUpdatedBy($this->getUser())
            ->setUpdatedAt(\Alaress_Date_Format::now());

        $this->reportMapper->save($report);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminSummativeReport.getList'));
    }


    /**
     * Soft-delete a report
     *
     * @param Model $report
     * @return void
     * @throws MapperException
     * @throws RouteNotFound
     * @api POST /adminv2/summative-report/{reportId}/delete
     */
    public function postDelete(Model $report): void
    {
        $this->reportMapper->delete($report);

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminSummativeReport.getList'));
    }


    /**
     * Generate a report using its configured payload as settings for the pdfs
     *
     * @param Model $report
     * @return void
     * @throws RouteNotFound
     * @throws \DebugBar\DebugBarException
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \JsonException
     * @throws \Laminas\Uri\Exception\InvalidUriException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @api GET /adminv2/summative-report/{reportId}/generate
     */
    public function getGenerate(Model $report): void
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        /* Generate a (warnings, reportConfigs) stack
         */
        $reportConfigs = (new Payload\Url(
            $this->router,
            $urlBase,
            $this->termMapper,
            $this->roleMapper,
            $this->userBulkMapper,
            $this->userMapper
        ))->generate($report);

        $warnings = $reportConfigs['warnings'];
        $reports = $reportConfigs['reportConfigs'];

        $numReports = \count($reports);

        /* If there's something to send to taskd, then
         * set up all the credentials and punt it across
         */
        if ($numReports) {
            // Get the taskd context, and send
            /** @var Producer $producer */
            $producer = $this->getDi()->get('taskd.messageProducer');

            /** @var MessageFactory $messageFactory */
            $messageFactory = $this->getDi()->get('taskd.downloadPagesMessageFactory');
            $userSerializer = new ModelToArray();

            /* armatronic:
             * Not sure what this does/did...?
             */
            foreach ($reports as $current) {
                $messageFactory = $messageFactory->addPdfContent($current['filename'], $current['url']);
            }

            /* This is the filename for the zip that contains everything
             * It's a concat of today's date and the report name
             */
            $outfile = mb_substr(sprintf(
                '%s-%s',
                \Alaress_Date_Format::now()->getDatabaseDate(),
                $report->getTitle()
            ), 0, Payload\Url::FILENAME_MAX_LENGTH, 'UTF-8') . '.zip';

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
                        $report->getTitle(),
                    ),
                    'from'    => call_user_func($userSerializer, $this->getUser()),
                    'to'      => call_user_func($userSerializer, $this->getUser()),
                ])
                ->setPdfFooter(file_get_contents(__DIR__ . self::FOOTER_HTML))
                ->build($outfile);

            /* Send it to task for async processing
             */
            $producer->send($message);
        }

        /* Show waiting screen
         */
        $this->getResponse()->content->set([
            'template' => '@adminSummativeReport/result.twig',
            'data' => [
                'pageCurrent'    => $this->router->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs(
                    $this->router->generate('adminSummativeReport.getList')
                ),
                'reportCount'    => $numReports,
                'warnings'       => $warnings,
            ]
        ]);
    }


    /**
     * Generates report for a particular $student,
     * using the options set in $report
     *
     * https://vagrant.dev.schoolbox.cloud/adminv2/summative-report/2/getStudentReport/25?startDate=2015-01-01&endDate=2020-12-31&yearLevels=1,2,3&campuses=1&students=45,6,25&summaryActive=1&summaryHeading=werI%20am%20a%20cat&summaryWorkType=1,2,3,5,6,7,8,9,10,11&summaryOverallGrade=1&summaryComments=1&summaryRubrics=1&classActive=0&classWorkType=1,2,3,5,6,7,8,9,10,11,12&classOverallGrade=1&classLearningLevels=1&classCohort=1&classCapabilityGrades=1&classProgression=1&classComments=1&classRubrics=1
     *
     * @param Model $report
     * @param User  $student
     * @return void
     * @throws MapperException
     * @throws \Alaress\Web\Controller\Exception
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @api GET /adminv2/summative-report/student/:studentId
     */
    public function getStudentReport(\Schoolbox\Admin\SummativeReport\Model $report, User $student): void
    {
        $optionsPayload = $report->decode();

        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        /* This payload instance can tell you things about the settings
         * Use it to find the start/end date span
         */
        $range = (new Payload\Url(
            $this->router,
            $urlBase,
            $this->termMapper,
            $this->roleMapper,
            $this->userBulkMapper,
            $this->userMapper
        ))->calculateDateRange($optionsPayload['terms']);

        $from = new \Alaress_Date_Format($range['startDate'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $to   = new \Alaress_Date_Format($range['endDate'], \Alaress_Date_Format::DATABASE_DATE_FORMAT);

        /* Manufacture a placeholder instance
         */
        $term = $this->termMapper->createModel()->setStart($from)->setActualFinish($to);

        /* View As
         * Default to the current admin
         */
        $viewer = $this->getUser();

        switch ($optionsPayload['viewAs']) {
            case Role::STUDENT:
                $viewer = $student;
                break;
            case Role::PARENT:
                $student->loadParents();

                /* No parents found for the current guy
                 * Exit - nothing to render
                 */
                if (!$student->getParents()->count()) {
                    $this->sendError(sprintf(
                        'Unable to generate report for student id %d (%s). No parents.',
                        $student->getId(),
                        $student->getExternalId()
                    ));
                }

                /* View as the first parent in the stack...whoever that might be
                 */
                $viewer = $student->getParents()->first();
                break;
            case Role::STAFF:
            default:
                break;
        }

        /* This helps us micromanage assessments
         */
        $gradeHelper = new Helper(
            $this->folderMapper,
            $this->assessmentMapperProxy,
            $this->access,
            new Factory($this->getConfigLoader())
        );

        [ $folders, $markbooks, $assessments ] = $gradeHelper->generateGrades($viewer, $student, $term, $from, $to);

        /* Find the assessments that fulfil $summaryWorkTypes requirements
         */
        $summaryAssessments = new Collection();

        if (\count($optionsPayload['summary']['workType'])) {
            $summaryWorkTypes = $this->workTypeMapper->findByColumn('id', $optionsPayload['summary']['workType']);

            /* Instantiate the Work Type instances for each of the sections in the report:
             * Summary and Class Pages
             */
            if ($summaryWorkTypes->count() > 0) {
                $summaryAssessments = $gradeHelper->filterAssessments($viewer, $student, $assessments, $summaryWorkTypes);
            }
        }

        $summaryAssessmentFeedbacks = $this->collateFeedbacks($summaryAssessments, $student, $viewer, (bool)$optionsPayload['summary']['rubrics']);

        /* Find the assessments that fulfil $classWorkTypes requirements
         */
        $classAssessments = new Collection();

        if (\count($optionsPayload['class']['workType'])) {
            $classWorkTypes = $this->workTypeMapper->findByColumn('id', $optionsPayload['class']['workType']);

            /* Instantiate the Work Type instances for each of the sections in the report:
             * Summary and Class Pages
             */
            if ($classWorkTypes->count() > 0) {
                $classAssessments = $gradeHelper->filterAssessments($viewer, $student, $assessments, $classWorkTypes);
            }
        }

        $classAssessmentFeedbacks = $this->collateFeedbacks($classAssessments, $student, $viewer, (bool)$optionsPayload['class']['rubrics']);

        /* Build it
         */
        $data = [
            'student'                    => $student,
            'folders'                    => $folders,
            'markbooks'                  => $markbooks,
            'summaryAssessments'         => $summaryAssessments,
            'classAssessments'           => $classAssessments,
            'summaryAssessmentFeedbacks' => $summaryAssessmentFeedbacks,
            'classAssessmentFeedbacks'   => $classAssessmentFeedbacks,
            'report'                     => $report,
            'showSummary'                => (bool)$optionsPayload['summary']['active'],
            'summaryHeading'             => trim((string)$optionsPayload['summary']['heading']),
            'showSummaryOverallGrade'    => (bool)$optionsPayload['summary']['overallGrade'],
            'showSummaryGrades'          => (bool)$optionsPayload['summary']['grades'],
            'showSummaryComments'        => (bool)$optionsPayload['summary']['comments'],
            'showSummaryRubrics'         => (bool)$optionsPayload['summary']['rubrics'],
            'showClass'                  => (bool)$optionsPayload['class']['active'],
            'showClassOverallGrade'      => (bool)$optionsPayload['class']['overallGrade'],
            'showClassGrades'            => (bool)$optionsPayload['class']['grades'],
            'showClassComments'          => (bool)$optionsPayload['class']['comments'],
            'showClassRubrics'           => (bool)$optionsPayload['summary']['rubrics'],

            /* Extract any custom summative report styles from Admin Skin Config
             * and slam them in here so schools can tweak our designs
             */
            'customReportStyle'          => (new CustomStyleExtractor())->extract((string)$this->getConfigLoader()->get('skin_custom_css'))
        ];

        $this->getResponse()->content->set(
            $this->buildUnthemedHtmlOutput('@adminSummativeReport/studentReport.twig', $data)
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
     * if there are reports to generate, send the user to the preview, or just show an error message
     *
     * @param Model $report
     * @throws RouteNotFound
     * @throws \JsonException
     */
    public function getPreview(Model $report)
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        /* Generate a (warnings, reportConfigs) stack
         */
        $reportConfigs = (new Payload\Url(
            $this->router,
            $urlBase,
            $this->termMapper,
            $this->roleMapper,
            $this->userBulkMapper,
            $this->userMapper
        ))->generate($report);

        $reports = $reportConfigs['reportConfigs'];

        //if report list is empty, display nice error message
        if (0 === count($reports)) {
            throw new Exception("There are currently no reports to preview that match the criteria selected.");
        } else {
            //if reports exist.
            //redirect user to report preview
            $this->getResponse()->redirect->to($reports[0]['url']);
        }
    }
}
