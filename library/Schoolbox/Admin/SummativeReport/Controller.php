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
use DebugBar\DebugBarException;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Admin\SummativeReport\Payload\VictorianProgression;
use Schoolbox\Admin\SummativeReport\Payload\SubjectOverview;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as ImplementationMapper;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\Grades\AssessmentComparator;
use Schoolbox\Learning\Grades\Cohort\ClassResults;
use Schoolbox\Learning\Grades\Cohort\ValidUseCohortMarks;
use Schoolbox\Learning\Grades\Helper;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserBulkMapper;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Twig\Error\Error;

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
     * Session key and url parameter which remembers the status
     * of a particular taskd message we generated here
     */
    public const SUMMATIVE_REPORT_STATUS_KEY = 'summativeReport_%s';


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
     * Constructor
     *
     * @param \Schoolbox\Error\Mapper $errorMapper
     * @param Mapper $reportMapper
     * @param UserMapper $userMapper
     * @param TermMapper $termMapper
     * @param YearLevelMapper $yearLevelMapper
     * @param CampusMapper $campusMapper
     * @param WorkTypeMapper $workTypeMapper
     * @param FolderMapper $folderMapper
     * @param StateMapper $stateMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param RubricDataSource $rubricDataSource
     * @param Access $access
     * @param UserBulkMapper $userBulkMapper
     * @param RoleMapper $roleMapper
     * @param ConfigMapper $configMapper
     * @param FieldStrategyFactory $fieldStrategyFactory
     * @param Rebuilder $rebuilder
     * @param CacheInterface $oidcCache
     * @param \Schoolbox\External\User $externalUser
     * @param ImplementationMapper $implementationMapper
     */
    public function __construct(
        private \Schoolbox\Error\Mapper $errorMapper,
        private Mapper $reportMapper,
        private UserMapper $userMapper,
        private TermMapper $termMapper,
        private YearLevelMapper $yearLevelMapper,
        private CampusMapper $campusMapper,
        private WorkTypeMapper $workTypeMapper,
        private FolderMapper $folderMapper,
        private \Schoolbox\Learning\DueWork\StateMapper $stateMapper,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private RubricDataSource $rubricDataSource,
        private Access $access,
        private UserBulkMapper $userBulkMapper,
        private RoleMapper $roleMapper,
        ConfigMapper $configMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache,
        private \Schoolbox\External\User $externalUser,
        private ImplementationMapper $implementationMapper
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }


    /**
     * A list of summative reports
     *
     * @return void
     * @throws Error
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

        $this->response = $this->responseFromTemplate(
            '@adminSummativeReport/list.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminSummativeReport.getList')),
                'reports' => $reports
            ]
        );
    }


    /**
     * The form you use to create a report
     *
     * @return void
     * @throws Error
     * @throws MapperException
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

        /**
         * grab all available worktypes.
         */
        $worktypes = [];
        foreach ($this->workTypeMapper->findAll() as $summaryWorkType) {
            $worktypes[] = [
                'id'   => $summaryWorkType->getId(),
                'name' => $summaryWorkType->getName(),
                'none' => true,
                'grade' => false,
                'comment' => false,
                'rubric' => false,
            ];
        }
        $report = $this->reportMapper->createModel();
        $report->setFileName(Model::DEFAULT_FILENAME);
        $this->response = $this->responseFromTemplate(
            '@adminSummativeReport/create.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminSummativeReport.getList')),
                'report' => $report,
                'options' => Model::DEFAULT_OPTIONS,
                'viewAs' => self::AVAILABLE_ROLETYPES,
                'logoZone' => $logoZone->getTwigContext(),
                'worktypesSummary' => $worktypes,
                'worktypesClass' => $worktypes
            ]
        );
    }


    /**
     * Create a new summative report
     *
     * @return void
     * @throws Exception
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
        /** @var Model $report **/
        $report = $this->reportMapper->createModel();

        $this->postUpdate($report);
    }


    /**
     * The form you use to update a report
     *
     * @param Model $report
     * @return void
     * @throws RouteNotFound
     * @throws DebugBarException
     * @throws \JsonException
     * @throws Error
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
        $payload = $this->decompilePayload($report);
        /**
         * grab all available worktypes.
         */
        $allWorktypes = $this->workTypeMapper->findAll();
        $worktypesSummary = [];
        $worktypesClass = [];
        foreach ($allWorktypes as $summaryWorkType) {
            $worktypesSummary[] = [
                'id'   => $summaryWorkType->getId(),
                'name' => $summaryWorkType->getName(),
                'grades' => $payload['summary']['workType'][$summaryWorkType->getId()]['grades'] ?? false,
                'comments' => $payload['summary']['workType'][$summaryWorkType->getId()]['comments'] ?? false,
                'rubrics' => $payload['summary']['workType'][$summaryWorkType->getId()]['rubrics'] ?? false,
                 // if we have seen this worktype in the payload, then set to show, and use it
                'show' => isset($payload['summary']['workType'][$summaryWorkType->getId()]),
            ];
            $worktypesClass[] = [
                'id'   => $summaryWorkType->getId(),
                'name' => $summaryWorkType->getName(),
                'grades' => $payload['class']['workType'][$summaryWorkType->getId()]['grades'] ?? false,
                'comments' => $payload['class']['workType'][$summaryWorkType->getId()]['comments'] ?? false,
                'rubrics' => $payload['class']['workType'][$summaryWorkType->getId()]['rubrics'] ?? false,
                // if we have seen this worktype in the payload, then set to show, and use it
                'show' => isset($payload['class']['workType'][$summaryWorkType->getId()]),
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@adminSummativeReport/create.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminSummativeReport.getList')),
                    [['Modify', null]]
                ),
                'report' => $report,
                'options' => $payload,
                'viewAs' => self::AVAILABLE_ROLETYPES,
                'logoZone' => $logoZone->getTwigContext(),
                'worktypesSummary' => $worktypesSummary,
                'worktypesClass' => $worktypesClass
            ]
        );
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
        $postData = $this->requestPost->getArrayCopy();

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

        $options = array_intersect_key($postData, $payloadKeys);
        // strip out the show `off` entries
        foreach ($options['class']['workType'] as $id => $workType) {
            if ("0" === $workType['show']) {
                unset($options['class']['workType'][$id]);
            } else {
                unset($options['class']['workType'][$id]['show']);
            }
        }
        foreach ($options['summary']['workType'] as $id => $workType) {
            if ("0" === $workType['show']) {
                unset($options['summary']['workType'][$id]);
            } else {
                unset($options['summary']['workType'][$id]['show']);
            }
        }

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
            ->setOptions(json_encode($options, JSON_THROW_ON_ERROR))
            ->setUpdatedBy($this->getUser())
            ->setFileName($report->sanitiseFilename(trim((string)$postData['filename'])));

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

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminSummativeReport.getList'));
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

        $this->response = $this->responseAsJson($output);
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

        $this->response = $this->responseAsJson($output);
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

        $this->response = $this->responseAsJson($output);
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

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminSummativeReport.getList'));
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

        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminSummativeReport.getList'));
    }


    /**
     * Generate a report using its configured payload as settings for the pdfs
     *
     * @param Model $report
     * @return void
     * @throws RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @api GET /adminv2/summative-report/{reportId}/generate
     */
    public function getGenerate(Model $report): void
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        /* Generate a (warnings, reportConfigs) stack
         */
        $reportConfigs = (new Payload\Url(
            $this->routeGenerator,
            $urlBase,
            $this->termMapper,
            $this->roleMapper,
            $this->userBulkMapper,
            $this->userMapper,
            $this->externalUser
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
                    (string)$urlBase
                        ->withPath(
                            $this->routeGenerator->generate(
                                'apiusernotify.apiUserNotify',
                                ['userId' => $this->getUser()->getId()]
                            )
                        )
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
                ->setOutfile($outfile)
                ->build();

            /* Send it to task for async processing
             */
            $producer->send($message);
        }

        /* Arbitrarily assign a guid to this report generation
         */
        $key = strtoupper(md5(uniqid((string)mt_rand(), true)));
        $reportKey = sprintf(self::SUMMATIVE_REPORT_STATUS_KEY, $key);

        /* Remember the result payload associated with this report generation
         */
        $statusPayload = [
            'numReports' => $numReports,
            'warnings' => $warnings
        ];

        $this->getSession()->set($reportKey, $statusPayload);

        /* Show waiting screen for the result payload associated with this report generation
         */
        $this->response = $this->redirectFound($this->routeGenerator->generate('adminSummativeReport.getStatus', [
            'reportId' => $report->getId(),
            'key' => $key
        ]));
    }


    /**
     * Show waiting screen. This is split from getGenerate() so that refreshing
     * doesn't perpetuate new taskd processes for impatient souls
     *
     * @param string $key
     * @return void
     * @throws Error
     * @throws NotFound
     * @throws RouteNotFound
     */
    public function getStatus(string $key): void
    {
        /* Corresponds to that which was set in getGenerate()
         * This usage prevents people inspecting the contents of one's session
         */
        $reportKey = sprintf(self::SUMMATIVE_REPORT_STATUS_KEY, $key);
        $statusPayload = $this->getSession()->get($reportKey);

        /* Summative Report keys are tied to a session; if you log out
         * and return to this url, the status paylod will be empty.
         * Similarly if you steal someone else's url and visit it
         *
         * Just 404 that, because it's a transient screen anyway.
         */
        if (null === $statusPayload) {
            throw new NotFound();
        }

        $this->response = $this->responseFromTemplate(
            '@adminSummativeReport/result.twig',
            [
                'pageCurrent'    => $this->routeGenerator->generate('adminSummativeReport.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs(
                    $this->routeGenerator->generate('adminSummativeReport.getList')
                ),
                'reportCount'    => $statusPayload['numReports'],
                'warnings'       => $statusPayload['warnings'],
            ]
        );
    }


    /**
     * Generates report for a particular $student,
     * using the options set in $report
     *
     * https://vagrant.dev.schoolbox.cloud/adminv2/summative-report/2/getStudentReport/25?startDate=2015-01-01&endDate=2020-12-31&yearLevels=1,2,3&campuses=1&students=45,6,25&summaryActive=1&summaryHeading=werI%20am%20a%20cat&summaryWorkType=1,2,3,5,6,7,8,9,10,11&summaryOverallGrade=1&summaryComments=1&summaryRubrics=1&classActive=0&classWorkType=1,2,3,5,6,7,8,9,10,11,12&classOverallGrade=1&classLearningLevels=1&classCohort=1&classCapabilityGrades=1&classProgression=1&classComments=1&classRubrics=1
     *
     * @param Model $report
     * @param User $student
     * @return void
     * @throws DefinitionException
     * @throws Error
     * @throws MapperException
     * @throws \Alaress\Web\Controller\Exception
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \UnexpectedValueException
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
            $this->routeGenerator,
            $urlBase,
            $this->termMapper,
            $this->roleMapper,
            $this->userBulkMapper,
            $this->userMapper,
            $this->externalUser
        ))->calculateDateRange($optionsPayload['terms']);

        /* Manufacture a placeholder instance
         */
        $term = $this->termMapper->createModel()
            ->setStart(new \Alaress_Date_Format($range['startDate'], \Alaress_Date_Format::DATABASE_DATE_FORMAT))
            ->setActualFinish(new \Alaress_Date_Format($range['endDate'], \Alaress_Date_Format::DATABASE_DATE_FORMAT));

        /* View As
         * Default to the current admin
         */
        $viewer = $this->getUser();

        switch ($optionsPayload['viewAs']) {
            case Role::STUDENT:
                $viewer = $student;
                break;
            case Role::PARENT:
                /* No parents found for the current guy
                 * Exit - nothing to render
                 */
                $this->externalUser->loadParents([$student]);
                $parents = $student->getParents();
                // make sure to load students of the parent, by default children are not populated for parents
                $this->externalUser->loadChildren($parents);
                if (!$parents->count()) {
                    $this->sendError(sprintf(
                        'Unable to generate report for student id %d (%s). No parents.',
                        $student->getId(),
                        $student->getExternalId()
                    ));
                    return;
                }

                /* View as the first parent in the stack...whoever that might be
                 */
                $viewer = $parents->first();
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

        [ $folders, $markbooks, $assessments ] = $gradeHelper->generateGrades($viewer, $student, $term);

        /* Find the assessments that fulfil $summaryWorkTypes requirements
         */
        $summaryAssessments = new GenericCollection(IAssessable::class);

        if (\count($optionsPayload['summary']['workType'])) {
            $summaryWorkTypes = $this->workTypeMapper->findByColumn('id', array_keys($optionsPayload['summary']['workType']));

            /* Instantiate the Work Type instances for each of the sections in the report:
             * Summary and Class Pages
             */
            if ($summaryWorkTypes->count() > 0) {
                $summaryAssessments = $gradeHelper->filterAssessments($viewer, $assessments, $summaryWorkTypes);
            }
        }

        $summaryAssessmentFeedbacks = $this->collateFeedbacks($summaryAssessments, $student, $viewer, $optionsPayload['summary']['workType']);

        /* Find the assessments that fulfil $classWorkTypes requirements
         */
        $classAssessments = new GenericCollection(IAssessable::class);

        if (\count($optionsPayload['class']['workType'])) {
            $classWorkTypes = $this->workTypeMapper->findByColumn('id', array_keys($optionsPayload['class']['workType']));

            /* Instantiate the Work Type instances for each of the sections in the report:
             * Summary and Class Pages
             */
            if ($classWorkTypes->count() > 0) {
                $classAssessments = $gradeHelper->filterAssessments($viewer, $assessments, $classWorkTypes);
            }
        }
        /* If the config option lms_cohort_comparison is set,
         * instantiate a new CohortMarks instance
         *
         * Otherwise use the default Markbook instance
         *
         * These markbook instances provide the five-point summary for the graphs
         */
        $allGraphs = [
            'summary' => [],
            'class' => []
        ];

        $lmsCohortComparison = $this->getConfigLoader()->get('lms_cohort_comparison');
        $gradientEnable = $this->getConfigLoader()->get('grade_gradient_enable');

        // Let markbook calculate the statistics
        $markSchemeFactory = new Factory($this->getConfigLoader());

        // If the student does not have Comparitave graphs enabled for them, do not show the graph
        if ($student->getDisplayGradeGraph() || $viewer->getRole()->isStaff()) {
            if ($optionsPayload['summary']['cohort']) {
                foreach ($folders as $folder) {
                    // if the folder is empty, then don't attempt to generate a graph for it
                    if (!isset($summaryAssessments[$folder->getId()])) {
                        continue;
                    }
                    // for each folder build and work with that markbook and assessments
                    $overallMarkScheme = $markSchemeFactory->spawnForFolder($folder, $this->getUser()->getRole()->isStaff());
                    //markbooks need to be spawned again for each folder, as summary could have a different collection of worktypes to class
                    $filteredMarkbook                       = (new Markbook($summaryAssessments[$folder->getId()], $overallMarkScheme, $this->getUser()))->sortAssessables(new AssessmentComparator($student));
                    $allGraphs['summary'][$folder->getId()] = $this->generateCohortGraph(
                        $summaryAssessments[$folder->getId()],
                        $folder,
                        $filteredMarkbook,
                        $lmsCohortComparison,
                        $gradientEnable,
                        $student,
                        $viewer,
                        $term,
                        $gradeHelper
                    );
                }
            }

            if ($optionsPayload['class']['cohort']) {
                foreach ($folders as $folder) {
                    // if the folder is empty, then don't attempt to generate a graph for it
                    if (!isset($classAssessments[$folder->getId()])) {
                        continue;
                    }
                    // for each folder build and work with that markbook and assessments
                    $overallMarkScheme = $markSchemeFactory->spawnForFolder($folder, $this->getUser()->getRole()->isStaff());
                    //markbooks need to be spawned again for each folder, as class could have a different collection of worktypes to summary
                    $filteredMarkbook                     = (new Markbook($classAssessments[$folder->getId()], $overallMarkScheme, $this->getUser()))->sortAssessables(new AssessmentComparator($student));
                    $allGraphs['class'][$folder->getId()] = $this->generateCohortGraph(
                        $classAssessments[$folder->getId()],
                        $folder,
                        $filteredMarkbook,
                        $lmsCohortComparison,
                        $gradientEnable,
                        $student,
                        $viewer,
                        $term,
                        $gradeHelper
                    );
                }
            }
        }

        // order folders based on folder sequence (if it is set, else use title natural sort)
        $folders = $folders->sortByMultiFields(['sequence' => 'desc', 'title' => 'asc']);

        $classAssessmentFeedbacks = $this->collateFeedbacks($classAssessments, $student, $viewer, $optionsPayload['class']['workType']);

        /* Optional Victorian Curriculum Progression chart thing
         */
        $vicProgression = [
            /* Enable to show annotations in the preview window, for debugging purposes
             * It saves you having to make a code change
             */
            'annotate' => (bool)$this->requestQuery->get('annotate', false),
            'summary' => null,
            'class' => null
        ];

        /* At least one of the Victorian Curriculum Progressions was enabled
         */
        if ($optionsPayload['summary']['vicProgression']['enable'] || $optionsPayload['class']['vicProgression']['enable']) {
            /* Assume we want the first-mentioned academic period. Schools can supply multiple,
             * but we only want one.
             */
            $academicPeriod = $this->termMapper->findById($optionsPayload['terms'][0]);

            /* The Summary Version
             */
            if ($optionsPayload['summary']['vicProgression']['enable']) {
                $vicProgression['summary'] = (new VictorianProgression\Payload(
                    $this->workTypeMapper,
                    $this->termMapper,
                    $this->stateMapper,
                    (int)$optionsPayload['summary']['vicProgression']['workType'],
                    $vicProgression['annotate']
                ))->generate($student, $academicPeriod, null);
            }

            /* The Class Version
             */
            if ($optionsPayload['class']['vicProgression']['enable']) {
                $generator = new VictorianProgression\Payload(
                    $this->workTypeMapper,
                    $this->termMapper,
                    $this->stateMapper,
                    (int)$optionsPayload['class']['vicProgression']['workType'],
                    $vicProgression['annotate']
                );

                /* Render a chart for each class in $folders, I suppose
                 */
                foreach ($folders as $folder) {
                    $folderId = (int)$folder->getId();

                    $vicProgression['class'][$folderId] = $generator->generate($student, $academicPeriod, $folderId);
                }
            }
        }

        // Subject Overviews
        /**
         * only fetch subject overviews if the option is enabled and the search term is not empty
         */
        $subjectOverviews = [];
        if ('1' === ($optionsPayload['class']['subjectOverview'] ?? '0') and '' !== trim((string)$optionsPayload['class']['subjectOverviewSearch'])) {
            /** @var Collection|Folder\Model[] $folders */
            $folderIds = $folders->getPropertyValues('id');
            if (!empty($folderIds)) {
                $subjectOverviews = (new SubjectOverview\Payload($this->implementationMapper))->generate($folderIds, $optionsPayload['class']['subjectOverviewSearch']);
            }
        }

        /* Need this to fetch the Govt. Student Number and slap it onto summative reports
         * Your connector might not supply this, so could be entirely null for all we know
         */
        $externalUser = [];
        $govtStudentNumber = null;

        if ('' !== trim((string)$student->getExternalId())) {
            $externalUser = (new \External_User())->getStudent($student->getExternalId());
            $govtStudentNumber = $externalUser['StudentGovernmentStudentNumber'] ?? null;
        }

        /* Build it
         */
        $data = [
            'subjectOverviews'           => $subjectOverviews,
            'student'                    => $student,
            'govtStudentNumber'          => $govtStudentNumber,
            'folders'                    => $folders,
            'markbooks'                  => $markbooks,
            'summaryAssessments'         => $summaryAssessments,
            'classAssessments'           => $classAssessments,
            'summaryAssessmentFeedbacks' => $summaryAssessmentFeedbacks,
            'classAssessmentFeedbacks'   => $classAssessmentFeedbacks,
            'report'                     => $report,
            'allGraphs'                  => $allGraphs,
            'showSummary'                => (bool)$optionsPayload['summary']['active'],
            'summaryHeading'             => trim((string)$optionsPayload['summary']['heading']),
            'showSummaryOverallGrade'    => (bool)$optionsPayload['summary']['overallGrade'],
            'summaryOptions'             => $optionsPayload['summary'],
            'classOptions'               => $optionsPayload['class'],
            'showClass'                  => (bool)$optionsPayload['class']['active'],
            'showClassOverallGrade'      => (bool)$optionsPayload['class']['overallGrade'],
            /* Optional Victorian Curriculum Progression chart thing
             * The $annotate var lets you see how we derived values, so you can look at the
             * preview window in a staging environment without needing code changes to reveal
             */
            'vicProgression'             => $vicProgression,
            /* Extract any custom summative report styles from Admin Skin Config
             * and slam them in here so schools can tweak our designs
             */
            'customReportStyle'          => (new CustomStyleExtractor())->extract((string)$this->getConfigLoader()->get('skin_custom_css'))
        ];

        $this->response = $this->responseFromTemplateUnthemed('@adminSummativeReport/studentReport.twig', $data);
    }


    /**
     * Hydrate feedback and returns as a collection indexed by assessment id
     *
     * @param Collection $folderAssessments
     * @param User       $student
     * @param User       $viewer
     * @param array      $workTypeOptions
     * @return Collection
     */
    public function collateFeedbacks(Collection $folderAssessments, User $student, User $viewer, array $workTypeOptions): Collection
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
                    "1" === $workTypeOptions[$assessment->getWorkType()->getId()]['rubrics']
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
     * @throws Exception
     * @throws \JsonException
     */
    public function getPreview(Model $report): void
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        /* Generate a (warnings, reportConfigs) stack
         */
        $reportConfigs = (new Payload\Url(
            $this->routeGenerator,
            $urlBase,
            $this->termMapper,
            $this->roleMapper,
            $this->userBulkMapper,
            $this->userMapper,
            $this->externalUser
        ))->generate($report);

        $reports = $reportConfigs['reportConfigs'];

        //if report list is empty, display nice error message
        if (0 === count($reports)) {
            throw new Exception("There are currently no reports to preview that match the criteria selected.");
        }

        //if reports exist.
        //redirect user to report preview
        $this->response = $this->redirectFound($reports[0]['url']);
    }

    /**
     *  Generate standard cohort graph similar to the grades page
     *
     * @param Collection $assessments
     * @param Folder\Model $folder
     * @param Markbook $filteredMarkbook
     * @param bool $lmsCohortComparison
     * @param bool $gradientEnable
     * @param User $student
     * @param User $viewer
     * @param Term\Model $term
     * @param Helper $gradeHelper
     *
     * @return array{graph: array, type: string}
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \LogicException
     * @throws \PDOException
     * @throws DefinitionException
     * @throws \UnexpectedValueException
     */
    private function generateCohortGraph(
        Collection $assessments,
        Folder\Model $folder,
        Markbook $filteredMarkbook,
        bool $lmsCohortComparison,
        bool $gradientEnable,
        User $student,
        User $viewer,
        Term\Model $term,
        Helper $gradeHelper
    ): array {
        if ((new ValidUseCohortMarks())->isSatisfiedBy($lmsCohortComparison, $folder)) {
            $graphType = 'cohort';
            $graph = (new ClassResults(
                $gradientEnable,
                $this->assessmentMapperProxy,
                $assessments,
                $viewer,
                $term,
            ))->collate($student);
        } else {
            $graphType = 'class';
            $graph = $gradeHelper->buildGraph($filteredMarkbook, $student, $viewer, $gradientEnable);
        }
        return [
            'graph' => $graph,
            'type'  => $graphType,
        ];
    }
}
