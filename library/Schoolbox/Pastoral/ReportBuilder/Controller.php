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
use Schoolbox\Pastoral\Report\Access\Mapper as ReportAccessMapper;
use Schoolbox\Pastoral\Report\Access\Model as ReportAccess;
use Schoolbox\Pastoral\Report\Dashboard\Mapper as PastoralReportDashboardMapper;
use Schoolbox\Pastoral\Report\Dashboard\Model as PastoralReportDashboard;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
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
        $selectedSummaryOptions = array_filter(
            PastoralReport::SUMMARY_TABLES_OPTIONS,
            function ($key) use ($report) {
                return in_array($key, explode(',', $report->getSummaryTables() ?? ''));
            },
            ARRAY_FILTER_USE_KEY
        );

        $mappedOptions = [];
        foreach ($selectedSummaryOptions as $key => $option) {
            $endpoint = $option['endpoint'] ?? null;
            // Replace {reportId} placeholder with actual report ID if endpoint exists
            if ($endpoint && str_contains($endpoint, '{reportId}')) {
                $endpoint = str_replace('{reportId}', (string)$report->getId(), $endpoint);
            }
            $mappedOptions[] = [
                'title' => $option['label'],
                'type' => $option['type'],
                'key' => $key,
                'endpoint' => $endpoint
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
     * @param PastoralReport $report
     * @throws MapperException
     */
    public function getReportStudentByType(PastoralReport $report): void
    {
        $records = $this->getReportData($report);

        // Extract unique types and subtypes from the records in a single pass
        $types = [];
        $subtypes = [];
        $typesWithSubtypes = [];

        /** @var PastoralRecord $record */
        foreach ($records as $record) {
            $type = $record->getType();
            if ($type) {
                $typeId = $type->getId();
                if (!isset($types[$typeId])) {
                    $types[$typeId] = $type;
                }
            }

            $subtype = $record->getSubType();
            if ($subtype) {
                $subtypeId = $subtype->getId();
                if (!isset($subtypes[$subtypeId])) {
                    $subtypes[$subtypeId] = $subtype;
                    // Track which types have subtypes for later processing
                    $typesWithSubtypes[$subtype->getPastoralType()->getId()] = true;
                }
            }
        }

        // Calculate counts and points for all types/subtypes and group by student in a single pass
        $subtypeCounts = [];
        $subtypePoints = [];
        $typeCounts = [];
        $typePoints = [];
        $studentRecords = [];

        foreach ($records as $record) {
            $student = $record->getStudent();
            $studentId = $student->getId();
            $points = $record->getPoints();
            $pointsValue = ($points !== null && $points !== '') ? (int)$points : 0;

            // Initialize student record if not exists
            if (!isset($studentRecords[$studentId])) {
                $studentRecords[$studentId] = [
                    'student' => $student,
                    'subtypeCounts' => [],
                    'subtypePoints' => [],
                    'typeCounts' => [],
                    'typePoints' => [],
                ];
            }

            // Process subtypes
            $subtype = $record->getSubType();
            if ($subtype) {
                $subtypeId = $subtype->getId();

                // Increment global counts
                $subtypeCounts[$subtypeId] = ($subtypeCounts[$subtypeId] ?? 0) + 1;
                $subtypePoints[$subtypeId] = ($subtypePoints[$subtypeId] ?? 0) + $pointsValue;

                // Increment student counts
                $studentRecords[$studentId]['subtypeCounts'][$subtypeId] =
                    ($studentRecords[$studentId]['subtypeCounts'][$subtypeId] ?? 0) + 1;
                $studentRecords[$studentId]['subtypePoints'][$subtypeId] =
                    ($studentRecords[$studentId]['subtypePoints'][$subtypeId] ?? 0) + $pointsValue;
            }

            // Process types (only those without subtypes)
            $type = $record->getType();
            if ($type && !isset($typesWithSubtypes[$type->getId()])) {
                $typeId = $type->getId();

                // Increment global counts
                $typeCounts[$typeId] = ($typeCounts[$typeId] ?? 0) + 1;
                $typePoints[$typeId] = ($typePoints[$typeId] ?? 0) + $pointsValue;

                // Increment student counts
                $studentRecords[$studentId]['typeCounts'][$typeId] =
                    ($studentRecords[$studentId]['typeCounts'][$typeId] ?? 0) + 1;
                $studentRecords[$studentId]['typePoints'][$typeId] =
                    ($studentRecords[$studentId]['typePoints'][$typeId] ?? 0) + $pointsValue;
            }
        }

        // Sort types by sequence and build table columns in the correct order
        $typesSorted = [];
        foreach ($types as $type) {
            $typesSorted[$type->getSequence()] = $type;
        }
        ksort($typesSorted);

        // Build table columns and total row data arrays
        $tableColumns = [];
        $totalRowData = [];

        foreach ($typesSorted as $type) {
            $typeId = $type->getId();

            if (isset($typesWithSubtypes[$typeId])) {
                // This type has subtypes - sort and add all subtypes for this type
                $typeSubtypes = [];
                foreach ($subtypes as $subtype) {
                    if ($subtype->getPastoralType()->getId() === $typeId) {
                        $typeSubtypes[$subtype->getSequence()] = $subtype;
                    }
                }
                ksort($typeSubtypes);

                foreach ($typeSubtypes as $subtype) {
                    $subtypeId = $subtype->getId();
                    $tableColumns[] = [
                        'key' => 'subtype-' . $subtypeId,
                        'title' => $subtype->getPastoralType()->getName(),
                        'subTitle' => $subtype->getName(),
                        'type' => 'RecordsAndPoints',
                        'isSortable' => true,
                    ];
                    $totalRowData[] = [
                        'records' => $subtypeCounts[$subtypeId] ?? 0,
                        'points' => $subtypePoints[$subtypeId] ?? 0,
                    ];
                }
            } else {
                // This type has no subtypes - add the type itself
                $tableColumns[] = [
                    'key' => 'type-' . $typeId,
                    'title' => $type->getName(),
                    'type' => 'RecordsAndPoints',
                    'isSortable' => true,
                ];
                $totalRowData[] = [
                    'records' => $typeCounts[$typeId] ?? 0,
                    'points' => $typePoints[$typeId] ?? 0,
                ];
            }
        }

         // Build table headers structure
         $tableHeaders = [
            [
                'key' => 'user',
                'title' => 'Name',
                'type' => 'User',
                'isSortable' => true,
            ],
            [
                'key' => 'total',
                'title' => 'Total',
                'type' => 'RecordsAndPoints',
                'isSortable' => true,
            ],
        ];
        $tableHeaders = array_merge($tableHeaders, $tableColumns);

        // Build student rows with data matching the column order
        $studentRows = [];
        foreach ($studentRecords as $studentData) {
            $student = $studentData['student'];

            // Start with user data
            $row = [[
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'externalId' => $student->getExternalId(),
                'firstname' => $student->getFirstname(),
                'fullName' => $student->getFullName(),
                'givenName' => $student->getGivenName(),
                'lastname' => $student->getLastname(),
                'preferredName' => $student->getPreferredName(),
                'title' => $student->getTitle(),
                'pronoun' => $student->getPronoun(),
                '_links' => [
                    'avatar' => '/portrait.php?id=' . $student->getId() . '&size=square64',
                    'profile' => '/search/user/' . $student->getId(),
                ],
            ]];

            // Calculate and add total column
            $totalRecords = array_sum($studentData['subtypeCounts']) + array_sum($studentData['typeCounts']);
            $totalPoints = array_sum($studentData['subtypePoints']) + array_sum($studentData['typePoints']);

            $row[] = [
                'records' => $totalRecords,
                'points' => $totalPoints,
            ];

            // Add data for each column in the same order as tableColumns
            foreach ($typesSorted as $type) {
                $typeId = $type->getId();

                if (isset($typesWithSubtypes[$typeId])) {
                    // Add subtypes for this type in sequence order
                    $typeSubtypes = [];
                    foreach ($subtypes as $subtype) {
                        if ($subtype->getPastoralType()->getId() === $typeId) {
                            $typeSubtypes[$subtype->getSequence()] = $subtype;
                        }
                    }
                    ksort($typeSubtypes);

                    foreach ($typeSubtypes as $subtype) {
                        $subtypeId = $subtype->getId();
                        $row[] = [
                            'records' => $studentData['subtypeCounts'][$subtypeId] ?? 0,
                            'points' => $studentData['subtypePoints'][$subtypeId] ?? 0,
                        ];
                    }
                } else {
                    // Add data for standalone type
                    $row[] = [
                        'records' => $studentData['typeCounts'][$typeId] ?? 0,
                        'points' => $studentData['typePoints'][$typeId] ?? 0,
                    ];
                }
            }

            $studentRows[] = $row;
        }

        // Sort student rows alphabetically by last name
        usort($studentRows, function($a, $b) {
            return strcmp($a[0]['lastname'], $b[0]['lastname']);
        });

        // Calculate grand totals for the summary row
        $grandTotalRecords = array_sum($subtypeCounts) + array_sum($typeCounts);
        $grandTotalPoints = array_sum($subtypePoints) + array_sum($typePoints);

        // Build total row structure
        $totalRow = [
            'Total', // User column
            [ // Total column
                'records' => $grandTotalRecords,
                'points' => $grandTotalPoints,
            ]
        ];
        $totalRow = array_merge($totalRow, $totalRowData);

        // Data payload with calculated values
        $payload = [
            'data' => [
                'tableHeaders' => $tableHeaders,
                'totalRow' => $totalRow,
                'rows' => $studentRows,
            ],
            'metadata' => [
                'count' => 20,
                'cursor' => [
                    'current' => null,
                    'next' => null,
                ],
            ],
        ];

        $this->response = $this->responseAsJson($payload);
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

        return $this->pastoralDataSource->getReportData(
            $this->getUser(),
            $beforeDate,
            $afterDate,
            $report->getSeverity(),
            $report->getTypes(),
            $report->getSubTypes(),
            $report->getAuthors(),
            $students,
            $report->getTags(),
            $report->getOrder(),
            $report->getInvolved(),
            $report->getYears(),
            $report->getHouses(),
            $report->getCampuses(),
            $report->getActions(),
            $report->getIncludeStudentsWithoutYear(),
            $report->getIncludeStudentsWithoutHouse(),
            $report->getIncludeRecordsWithoutAction(),
            $report->getIncludeArchivedRecords(),
            $report->getActionStatus(),
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
