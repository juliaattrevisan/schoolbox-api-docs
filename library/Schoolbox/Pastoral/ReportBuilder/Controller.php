<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Crell\ApiProblem\ApiProblem;
use HouseMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Audience\ColorHelper;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Model as PastoralRecord;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Report\Access\Mapper as ReportAccessMapper;
use Schoolbox\Pastoral\Report\Access\Model as ReportAccess;
use Schoolbox\Pastoral\Report\Dashboard\Mapper as PastoralReportDashboardMapper;
use Schoolbox\Pastoral\Report\Dashboard\Model as PastoralReportDashboard;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\ReportBuilder\TabType\TabTypeInterface;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Action\Mapper as ActionMapper;
use Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray as UserTranslator;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel;

class Controller extends Base
{
    /**
     * Constructor
     *
     * @param ErrorMapper                   $errorMapper
     * @param PastoralDataSource            $pastoralDataSource
     * @param PastoralTagMapper             $pastoralTagMapper
     * @param PastoralTypeMapper            $pastoralTypeMapper
     * @param PastoralSubTypeMapper         $pastoralSubTypeMapper
     * @param PastoralSeverityMapper        $pastoralSeverityMapper
     * @param HouseMapper                   $houseMapper
     * @param CampusMapper                  $campusMapper
     * @param PastoralGroupMapper           $pastoralGroupMapper
     * @param PastoralReportMapper          $pastoralReportMapper
     * @param PastoralReportDashboardMapper $pastoralReportDashboardMapper
     * @param Term\Mapper                   $termMapper
     * @param YearLevel\Mapper              $yearLevelMapper
     * @param ActionMapper                  $actionMapper
     * @param ReportAccessMapper            $reportAccessMapper
     * @param UserMapper                    $userMapper
     * @param RoleMapper                    $roleMapper
     * @param UserAccessMapper              $userAccessMapper
     * @param RosettaStone                  $l10n
     * @param ModelToArray                  $pastoralTranslator
     * @param UserTranslator                $userTranslator
     * @param PastoralMapper                $pastoralMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private PastoralDataSource $pastoralDataSource,
        private PastoralTagMapper $pastoralTagMapper,
        private PastoralTypeMapper $pastoralTypeMapper,
        private PastoralSubTypeMapper $pastoralSubTypeMapper,
        private PastoralSeverityMapper $pastoralSeverityMapper,
        private HouseMapper $houseMapper,
        private CampusMapper $campusMapper,
        private PastoralGroupMapper $pastoralGroupMapper,
        private PastoralReportMapper $pastoralReportMapper,
        private PastoralReportDashboardMapper $pastoralReportDashboardMapper,
        private Term\Mapper $termMapper,
        private YearLevel\Mapper $yearLevelMapper,
        private ActionMapper $actionMapper,
        private ReportAccessMapper $reportAccessMapper,
        private UserMapper $userMapper,
        private RoleMapper $roleMapper,
        private UserAccessMapper $userAccessMapper,
        private RosettaStone $l10n,
        private ModelToArray $pastoralTranslator,
        private UserTranslator $userTranslator,
        private PastoralMapper $pastoralMapper,
    ) {
    }

    /**
     * Display report builder saved reports
     */
    public function getIndex(): void
    {
        $reportsAll  = $this->pastoralReportMapper->findForUser($this->getUser(), dashboardOnly: false);
        $isModerator = $this->getUser()->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL) !== null;

        $reportsMine  = [];
        $reportsGroup = [];
        $reportsOther = [];

        /** @var PastoralReport $reportCurrent */
        foreach ($reportsAll as $reportCurrent) {
            if ($reportCurrent->getOwner()->getId() === $this->getUser()->getId()) {
                $reportsMine[] = $reportCurrent;
            } elseif (in_array($this->getUser()->getId(), $reportCurrent->getUserIds(ReportAccess::READ))) {
                $reportsGroup[] = $reportCurrent;
            } elseif ($isModerator) {
                $reportsOther[] = $reportCurrent;
            }
        }

        // And render this thing out

        $this->response = $this->responseFromTemplate(
            '@pastoralReportBuilder/index.twig',
            [
                'reportsMine'  => $reportsMine,
                'reportsGroup' => $reportsGroup,
                'reportsOther' => $reportsOther,
                'isModerator'  => $isModerator,
            ],
            'Pastoral Reports',
        );
    }

    /**
     * Create new pastoral report
     * @throws MapperException
     */
    public function getReportInsert(): void
    {
        $report = $this->pastoralReportMapper->createModel();
        $report->setOwner($this->getUser());

        $this->getReportUpdate($report);
    }

    /**
     * Display report builder page
     *
     * @param PastoralReport $report
     */
    public function getReportUpdate(PastoralReport $report): void
    {
        // These are required to populate the selectize

        // Students

        $studentsSelected = [];
        $yearPrefix       = $this->l10n->translate('year', false, true);

        /** @var User $student */
        foreach ($report->getStudents() as $student) {
            $year = $student->getYear() ? $student->getYear()->getLongName($yearPrefix) : '';

            $studentsSelected[] = [
                'id'   => $student->getId(),
                'name' => "{$student->getFullName()} - {$year} {$student->getRole()->getName()} " .
                    "({$student->getExternalId()})",
            ];
        }

        // Authors

        $authorsSelected = [];

        foreach ($report->getAuthors() as $author) {
            $year = $author->getYear() ? $author->getYear()->getName() : 'unknown';

            $authorsSelected[] = [
                'id'   => $author->getId(),
                'name' => "{$author->getFullName()} - Year {$year} {$author->getRole()->getName()} " .
                    "({$author->getExternalId()})",
            ];
        }

        // Tags

        $tagsSelected = [];

        foreach ($report->getTags() as $tag) {
            $tagsSelected[] = [
                'id'   => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        // Folders
        $foldersSelected = $report->getFolders()->map(
            static function (Folder $folder) {
                return [
                    'id'   => $folder->getId(),
                    'name' => $folder->getTitle(),
                ];
            }
        )->getAll();

        // Actions

        $actionsSelected  = [];
        $availableActions = [];

        if (!$this->getConfigLoader()->get($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED))) {
            foreach ($report->getActions() as $action) {
                $actionsSelected[] = [
                    'id'   => $action->getId(),
                    'name' => $action->getName(),
                ];
            }
            $availableActions = $this->actionMapper->findAll();
        }

        $orderFields = PastoralReport::ORDER_FIELDS;

        // Only show severity if it is enabled by policy
        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            // this will be localised in twig...
            $orderFields[PastoralReport::ORDER_SEVERITY] = 'Severity';
            $severities                                  = $this->pastoralSeverityMapper->findAll();
        }

        // Remove fields that are not supported
        $report->setOrder(
            $report->getOrder()->filter(static function (string $order) use ($orderFields) {
                return in_array($order, array_keys($orderFields));
            })
        );
        // Add order fields missing in the current report
        foreach (array_keys($orderFields) as $orderCurrent) {
            if (!in_array($orderCurrent, $report->getOrder()->getAll())) {
                $report->getOrder()->add($orderCurrent);
            }
        }

        try {
            $this->pastoralReportDashboardMapper->findOneByColumnList(
                [
                    'user_id'   => $this->getUser()->getId(),
                    'report_id' => $report->getId(),
                ]
            );

            $report->setDashboard(true);
        } catch (MapperException $e) {
            $report->setDashboard(false);
        }

        // Default to using relative date
        $report->setUsesRelativeDate($report->getUsesRelativeDate() ?? true);
        $report->setRelativeDateString($report->getRelativeDateString() ?? PastoralReport::THIS_SCHOOL_YEAR);

        /* A stack of all YearLevel instances, ordered by sequence from the admin area
         */
        $yearLevels = $this->yearLevelMapper->findAll();

        //This is fudged using the same audience screen from the record. So to get the saved records to display, it is easiest to attach them to $record
        $fakeRecord                         = [];
        $fakeRecord['id']                   = "fakeId";
        $fakeRecord['accessWithPermission'] = $this->reportAccessMapper->findByColumn('pastoral_report_id', $report->getId());
        $roles                              = $this->roleMapper->findByColumnList(
            [
                'group_type'      => 'staff',
                'access_pastoral' => true,
            ]
        );

        // And render this thing out
        $this->response = $this->responseFromTemplate(
            '@pastoralReportBuilder/modify.twig',
            [
                'moderator'            => $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL)->count() > 0,
                'years'                => $yearLevels,
                'houses'               => $this->houseMapper->findAll()->sortBy('sequence', 'asc'),
                'campuses'             => $this->campusMapper->findAll()->sortBy('sequence', 'asc'),
                'types'                => $this->pastoralTypeMapper->findAll(),
                'subTypes'             => $this->pastoralSubTypeMapper->findAll(),
                'severities'           => $severities,
                'tags'                 => $this->pastoralTagMapper->findAll(),
                'actions'              => $availableActions,
                'groups'               => $this->pastoralGroupMapper->findAll(['order' => ['name' => 'asc']]),
                'studentsSelected'     => $studentsSelected,
                'authorsSelected'      => $authorsSelected,
                'tagsSelected'         => $tagsSelected,
                'actionsSelected'      => $actionsSelected,
                'foldersSelected'      => $foldersSelected,
                'report'               => $report,
                'datePresets'          => PastoralReport::RELATIVE_DATE_PRESETS,
                'selectedRelativeDate' => $report->getRelativeDateString(),
                'record'               => $fakeRecord,
                'moderatorIds'         => $this->userMapper->findByAccess(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_READ)->getPropertyValues('id'),
                'roles'                => $roles,
                'orderFields'          => $orderFields,
                'summaryTablesOptions' => PastoralReport::SUMMARY_TABLES_OPTIONS,
            ],
            'Pastoral Report',
        );
    }

    /**
     * Retrieve report of all pastoral records with the selected tag
     *
     * @param PastoralTag $tag
     */
    public function getReportTags(PastoralTag $tag): void
    {
        $records = $this->pastoralDataSource->findByTag($this->getUser(), $tag);

        $showSeverity   = $this->getConfigLoader()->get('pastoral_severity');
        $severityColors = $showSeverity ? $this->getSeverityColors() : [];

        $defaultSummaryOptions = [
            ['title' => 'List', 'type' => 'list']
        ];

        $tabs = array_merge(
            $defaultSummaryOptions,
        );

        // And spit this thing out

        $this->response = $this->responseFromTemplate(
            '@pastoralReportBuilder/report_results.twig',
            [
                'title'          => "Records By Tag - {$tag->getName()}",
                'export'         => false,
                'results'        => $records->map($this->pastoralTranslator),
                'showSeverity'   => $showSeverity,
                'severityColors' => $severityColors,
                /* Summary tables tabs
                 */
                'tabs'           => $tabs,
            ],
            'Pastoral Tag Report',
        );
    }

    /**
     * Generate a pastoral report with the relevant filtering applied, this version has the accordion and pagination
     *
     * @param PastoralReport $report
     * @throws MapperException
     */
    public function getReportResults(PastoralReport $report): void
    {
        $records = $this->getReportData($report);

        // Keep an audit trail of any reports that are run

        $pastoralAuditMapper = PastoralAuditMapper::getInstance();

        $auditRecord = $pastoralAuditMapper->createModel();
        $auditRecord->setUser($this->getUser());
        $auditRecord->setReportId($report->getId());
        $pastoralAuditMapper->save($auditRecord);

        $showSeverity   = $this->getConfigLoader()->get('pastoral_severity');
        $severityColors = $showSeverity ? $this->getSeverityColors() : [];

        $defaultSummaryOptions = [
            ['title' => 'List', 'type' => 'list', 'key' => 'list', 'endpoint' => 'list']
        ];

        /* Find the selected list of options
         */
        $checkedSummaryOptions = explode(',', $report->getSummaryTables() ?? '');
        $selectedSummaryOptions = array_filter(
            PastoralReport::SUMMARY_TABLES_OPTIONS,
            function ($key) use ($checkedSummaryOptions) {
                return in_array($key, $checkedSummaryOptions);
            },
            ARRAY_FILTER_USE_KEY
        );

        $mappedOptions = [];
        foreach ($selectedSummaryOptions as $key => $option) {
            $mappedOptions[] = [
                'title' => $option['label'],
                'type' => $option['type'],
                'key' => $key,
                'endpoint' => $this->getRouteIfAuthorized(
                    'pastoralReportBuilder.getReportByTabType',
                    [
                        'reportId' => $report->getId(),
                        'report' => $report,
                        'tabType' => $key,
                        'resultType' => 'records',
                    ]
                )
            ];
        }

        $tabs = array_merge($defaultSummaryOptions, $mappedOptions);
        // And spit this thing out

        $this->response = $this->responseFromTemplate(
            '@pastoralReportBuilder/report_results.twig',
            [
                'title'          => $report->getName(),
                'export'         => true,
                'results'        => $records->map($this->pastoralTranslator),
                'report'         => $report,
                'showSeverity'   => $showSeverity,
                'severityColors' => $severityColors,
                /* Summary tables tabs
                 */
                'tabs'           => $tabs,
            ],
            $report->getName(),
        );
    }

    /**
     * Retrieve report of all pastoral records with the selected type
     * to display in a table format
     *
     * @param string         $tabType
     * @param PastoralReport $report
     */
    public function getReportByTabType(string $tabType, PastoralReport $report): void
    {
        $limit = $this->requestQuery->getInt('limit', Cursor::LIMIT_DEFAULT);

        try {
            $factory = (new Factory(
                $this->userTranslator,
                $this->userMapper,
                $this->termMapper,
                $this->pastoralMapper,
                $limit
            ))->produce(
                $this->prepareReportData($report),
                $tabType
            );

            if (null === $factory) {
                throw new MapperException("Invalid type: $tabType has been given.");
            }

            $records = $factory->retrieveResults(null);
            $records = $factory->process($records);
            $this->response = $this->responseAsJson($records);
        } catch (\Exception $e) {
            $problem = new ApiProblem($e->getMessage());
            $problem->setStatus($e->getCode());
            $this->response = $this->responseAsJson($problem->asArray());
        }
    }

    /**
     * Exports report data
     * @param PastoralReport $report
     */
    public function getReportResultsExport(PastoralReport $report): void
    {
        $records  = $this->getReportData($report);
        $exporter = new \Report_Exporter($report->getName());
        $headings = [
            'Pastoral ID'  => 'Pastoral ID',
            'Student Name' => 'Student Name',
            'Student ID'   => 'Student ID',
            'House'        => 'House',
            'Campus'       => 'Campus',
            'Year'         => 'Year',
            'Title'        => 'Title',
            'Type'         => 'Type',
            'Sub-Type'     => 'Sub-Type',
            'Occurred On'  => 'Occurred On',
        ];
        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $headings['Severity'] = 'Severity';
        }
        $headings['Tags'] = 'Tags';

        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_POINT_ENABLED)) {
            $headings['Points'] = 'Points';
        }

        $headings = array_merge($headings, [
            'Body'         => 'Body',
            'Created Date' => 'Created Date',
            'Created By'   => 'Created By',
        ]);

        $exporter->setHeadings($headings);

        // we need to modify the dataset to substitute resourceIDs with their actual names / descriptions
        $exporter->setData($records, [$this, 'dataManipulator']);
        $exporter->exportCSV();
    }

    /**
     * Manipulator for exporting report
     * @param PastoralRecord $record
     * @return array
     */
    public function dataManipulator(PastoralRecord $record): array
    {
        $points = trim((string)$record->getPoints());

        return [
            'Pastoral ID'  => $record->getId(),
            'Student Name' => $record->getStudent()->getFullName(),
            'Student ID'   => $record->getStudent()->getExternalId() ?? '',
            'House'        => $record->getStudent()->getSchoolHouse() ? $record->getStudent()->getSchoolHouse()->getHouse() : '',
            'Campus'       => implode(',', $record->getStudent()->getCampusCollection()->getPropertyValues('name')),
            'Year'         => $record->getStudent()->getYear() ? $record->getStudent()->getYear()->getName() : '',
            'Title'        => $record->getHeading(),
            'Type'         => $record->getType() ? $record->getType()->getName() : '',
            'Sub-Type'     => $record->getSubType() ? $record->getSubType()->getName() : '',
            'Occurred On'  => $record->getOccurredOn()?->getDatabaseDate(),
            'Tags'         => implode(',', $record->getTags()->getPropertyValues('name')),
            'Points'       => '' !== $points ? $points : '',
            'Severity'     => $record->getSeverity() ? $record->getSeverity()->getName() : '',
            'Body'         => $record->getBodyPublic(),
            'Created Date' => $record->getCreatedAt()->getDatabase(),
            'Created By'   => $record->getOwner()->getFullName(),
        ];
    }

    private function prepareReportData(PastoralReport $report): array
    {
        // Special case for the current term relative date, which is not a strtotime-able thingy
        if (!$report->getUsesRelativeDate()) {
            $beforeDate = $report->getBeforeDate();
            $afterDate  = $report->getAfterDate();
        } elseif ($report->getRelativeDateString() === PastoralReport::THIS_TERM) {
            $currentTerm = $this->termMapper->findForToday();
            $beforeDate  = $currentTerm->getActualFinish();
            $afterDate   = $currentTerm->getStart();
        } elseif ($report->getRelativeDateString() === PastoralReport::THIS_YEAR) {
            $now        = Alaress_Date_Format::now();
            $beforeDate = (clone $now)->endOfYear();
            $afterDate  = (clone $now)->startOfYear();
        } elseif ($report->getRelativeDateString() === PastoralReport::THIS_SCHOOL_YEAR) {
            $now        = Alaress_Date_Format::now();
            $beforeDate = (clone $now)->endOfSchoolYear();
            $afterDate  = (clone $now)->getStartOfSchoolYear();
        } else {
            $beforeDate = Alaress_Date_Format::now();
            $afterDate  = Alaress_Date_Format::newFromTimestamp(strtotime($report->getRelativeDateString()));
        }

        $students = $report->getStudents();
        foreach ($report->getFolders() as $folder) {
            try {
                $folderUsers = $this->userAccessMapper->findUsersInCategory(
                    $folder,
                    Role::STUDENT,
                    UserAccess::ACCESS_READ,
                    true,
                    false
                );
                $students    = $students->mergeUnique($folderUsers, 'id');
            } catch (MapperException $e) {
            }
        }

        // if folder is set, but no users returned, we should not return any records
        if (($report->getFolders()->count() > 0) && ($students->count() === 0)) {
            return $this->pastoralDataSource->createCollection();
        }

        return [
            'user' => $this->getUser(),
            'beforeDate' => $beforeDate,
            'afterDate' => $afterDate,
            'severity' => $report->getSeverity(),
            'types' => $report->getTypes(),
            'subtypes' => $report->getSubTypes(),
            'authors' => $report->getAuthors(),
            'students' => $students,
            'tags' => $report->getTags(),
            'orders' => $report->getOrder(),
            'involved' => $report->getInvolved(),
            'years' => $report->getYears(),
            'houses' => $report->getHouses(),
            'campuses' => $report->getCampuses(),
            'actions' => $report->getActions(),
            'includeStudentsWithoutYear' => $report->getIncludeStudentsWithoutYear(),
            'includeStudentWithoutHouse' => $report->getIncludeStudentsWithoutHouse(),
            'includeRecordsWithoutAction' => $report->getIncludeRecordsWithoutAction(),
            'includeArchivedRecords' => $report->getIncludeArchivedRecords(),
            'actionStatus' => $report->getActionStatus(),
        ];
    }

    /**
     * Get pastoral report records
     *
     * @param PastoralReport $report
     *
     * @return Collection
     */
    private function getReportData(PastoralReport $report): Collection
    {
        // Get matching records
        $data = $this->prepareReportData($report);

        return $this->pastoralDataSource->getReportData(
            $data['user'],
            $data['beforeDate'],
            $data['afterDate'],
            $data['severity'],
            $data['types'],
            $data['subtypes'],
            $data['authors'],
            $data['students'],
            $data['tags'],
            $data['orders'],
            $data['involved'],
            $data['years'],
            $data['houses'],
            $data['campuses'],
            $data['actions'],
            $data['includeStudentsWithoutYear'],
            $data['includeStudentWithoutHouse'],
            $data['includeRecordsWithoutAction'],
            $data['includeArchivedRecords'],
            $data['actionStatus'],
        );
    }

    /**
     * Delete pastoral report
     *
     * @param PastoralReport $report
     * @throws MapperException
     */
    public function postReportDelete(PastoralReport $report): void
    {
        $this->pastoralReportMapper->delete($report);
    }

    /**
     * Create pastoral report
     * @return void
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postReportCreate()
    {
        $pastoralReport = $this->pastoralReportMapper->createModel();
        $pastoralReport->setOwner($this->getUser());
        $this->postReportModify($pastoralReport);
    }

    /**
     * Update pastoral report
     * @param PastoralReport $report
     * @return void
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postReportModify(PastoralReport $report): void
    {
        $post = $this->requestPost;

        if (trim($post->get('name')) === '') {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        $report->setName($post->get('name'));
        $report->setDescription($post->get('description'));
        $report->setOrderCsv($post->get('order'));
        $report->setInvolved($post->getBool('involved', false));

        // Only save severity if it is enabled by policy
        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_SEVERITY_ENABLED)) {
            $report->setSeverity($this->pastoralSeverityMapper->createModel($post->get('severity')));
        }

        if (!empty($post->get('date-option')) && $post->get('date-option') === PastoralReport::ABSOLUTE_DATE) {
            $report->setUsesRelativeDate(false);

            if (!empty($post->get('before'))) {
                $report->setBeforeDate(Alaress_Date_Format::newLocalShort($post->get('before')));
            }

            if (!empty($post->get('after'))) {
                $report->setAfterDate(Alaress_Date_Format::newLocalShort($post->get('after')));
            }
        } else {
            $report->setUsesRelativeDate(true);
            $report->setRelativeDateString(PastoralReport::THIS_SCHOOL_YEAR);

            // Update with POSTed relative date if it's valid
            $relativeDateString = $post->get('relative-dates-dropdown');
            if (!empty($relativeDateString) && array_key_exists($relativeDateString, PastoralReport::RELATIVE_DATE_PRESETS)) {
                $report->setRelativeDateString($relativeDateString);
            }
        }

        $report->setIncludeArchivedRecords($post->getBool('includeArchivedRecords', false));

        $report->setTypesCsv($this->getCsv($post->get('types')));
        $report->setSubTypesCsv($this->getCsv($post->get('subtypes')));
        $report->setAuthorsCsv($this->getCsv($post->get('authors')));
        $report->setStudentsCsv($this->getCsv($post->get('students')));
        $report->setTagsCsv($this->getCsv($post->get('tags')));
        $report->setYearsCsv($this->getCsv($post->get('years')));
        $report->setHousesCsv($this->getCsv($post->get('houses')));
        $report->setCampusesCsv($this->getCsv($post->get('campuses')));
        $report->setFoldersCsv(empty($post->get('import_folders')) ? null : $post->get('import_folders'));

        $report->setIncludeStudentsWithoutYear($post->getBool('includeStudentsWithoutYear', false));
        $report->setIncludeStudentsWithoutHouse($post->getBool('includeStudentsWithoutHouse', false));

        // Saving the summary tables options
        $report->setSummaryTables(implode(',', $post->get('summaryTables', [])));

        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            $report->setActionsCsv($this->getCsv($post->get('actions')));
            $report->setIncludeRecordsWithoutAction($post->getBool('includeRecordsWithoutAction', false));
            $actionStatus = in_array($post->get('actionStatus'), [
                PastoralReport::ACTION_STATUS_PENDING,
                PastoralReport::ACTION_STATUS_COMPLETED,
            ])
                ? $post->get('actionStatus')
                : null;
            $report->setActionStatus($actionStatus);
        }

        // Save groups the report is shared with
        $report->setAccess($this->reportAccessMapper->createCollection());

        if (is_array($post->get('group') ?? false)) {
            $selectedGroups = $post->get('group');
            $validGroups    = $this->pastoralGroupMapper->findByColumn('id', array_keys($selectedGroups))->indexBy('id');
            foreach ($selectedGroups as $groupId => $checked) {
                if ($validGroups->offsetExists($groupId)) {
                    $groupAccess = $this->reportAccessMapper->createModel()
                                                            ->setGroup($validGroups->offsetGet($groupId))
                                                            ->setAccess((int)$checked)
                                                            ->setPastoralReport($report);
                    $report->getAccess()->add($groupAccess);
                }
            }
        }

        if (is_array($post->get('user') ?? false)) {
            $selectedUsers = $post->get('user');
            $validUsers    = $this->userMapper->findByColumn('id', array_keys($selectedUsers))->indexBy('id');
            foreach ($selectedUsers as $userId => $checked) {
                if ($validUsers->offsetExists($userId)) {
                    $userAccess = $this->reportAccessMapper->createModel()
                                                           ->setUser($validUsers->offsetGet($userId))
                                                           ->setAccess((int)$checked)
                                                           ->setPastoralReport($report);
                    $report->getAccess()->add($userAccess);
                }
            }
        }

        $report->setNotificationEnabled($post->getBool('notificationEnabled', false));

        $this->pastoralReportMapper->save($report);

        // Update display on pastoral dashboard status
        $this->updatePastoralDashboard($report, $post->getBool('dashboard', false));

        // Go back to the reports list

        $this->response = $this->redirectAfterPost(
            $this->routeGenerator->generate('pastoralReportBuilder.getIndex')
        );
    }

    /**
     * Update the display status of a report on the current users dashboard
     * @throws MapperException
     */
    public function postReportDashboardModify(): void
    {
        $post = $this->requestPost;

        // Create bare minimum report object

        $report = $this->pastoralReportMapper->createModel($post->get('reportId'));

        $result = $this->updatePastoralDashboard($report, (bool)$post->get('display'));

        $this->response = $this->responseAsJson([
                                                    'status' => 'OK',
                                                    'result' => $result,
                                                ]);
    }

    /**
     * Update pastoral dashboard record
     *
     * @param PastoralReport $report
     * @param bool           $display
     *
     * @return string
     * @throws MapperException
     */
    private function updatePastoralDashboard(PastoralReport $report, bool $display): string
    {
        try {
            // Check if the report is already on the dashboard

            /** @var PastoralReportDashboard $pastoralReportDashboard */
            $pastoralReportDashboard = $this->pastoralReportDashboardMapper->findOneByColumnList(
                [
                    'user_id'   => $this->getUser()->getId(),
                    'report_id' => $report->getId(),
                ]
            );

            // We found the report on the dashboard, check if we need to remove it

            if (!$display) {
                // Report was being displayed on dashboard, delete it

                $this->pastoralReportDashboardMapper->delete($pastoralReportDashboard);
                return 'Removed from dashboard';
            } else {
                return 'No action, already on dashboard';
            }
        } catch (MapperException\Deleted $e) {
            $pastoralReportDashboard = $e->getDeletedObject();
            $this->pastoralReportDashboardMapper->restoreSoftDeleted($pastoralReportDashboard);
            return 'Added to dashboard';
        } catch (MapperException $e) {
            // Report wasn't on the dashboard already, check if we need to add it

            if ($display) {
                // Add the report to the dashboard

                $pastoralReportDashboard = $this->pastoralReportDashboardMapper->createModel();
                $pastoralReportDashboard->setUser($this->getUser());
                $pastoralReportDashboard->setReport($report);
                $this->pastoralReportDashboardMapper->save($pastoralReportDashboard);
                return 'Added to dashboard';
            } else {
                return 'No action, already not on dashboard';
            }
        }
    }

    /**
     * @param array|null $var
     * @return string|null
     */
    private function getCsv(?array $var): ?string
    {
        return is_array($var) ? implode(',', $var) : null;
    }

    /**
     * Returns array of severities with colour in format of
     * severity id => ['back' => background colour, 'fore' => foreground colour]
     * @return array
     */
    private function getSeverityColors(): array
    {
        $severityColors = [];
        $severities     = $this->pastoralSeverityMapper->findAll(['deleted' => true])->indexBy('id');
        $transformer    = new ColorHelper();
        $colorFactory   = $transformer->getColorFactory();
        foreach ($severities as $severityId => $severity) {
            if (!$severity->getColor()) {
                continue;
            }
            $severityColors[$severityId] = [
                'back' => $severity->getColor(),
                'fore' => $transformer->calculateForeground($colorFactory->build($severity->getColor()))->getAsRgba(),
            ];
        }
        return $severityColors;
    }
}
