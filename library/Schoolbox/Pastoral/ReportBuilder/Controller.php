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
use Aura\Router\Exception\RouteNotFound;
use HouseMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Model as PastoralRecord;
use Schoolbox\Pastoral\Report\Dashboard\Mapper as PastoralReportDashboardMapper;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Action\Mapper as ActionMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\YearLevel;

class Controller extends Base
{
    /** @var ErrorMapper $errorMapper */
    private ErrorMapper $errorMapper;

    /** @var PastoralDataSource $pastoralDataSource */
    private PastoralDataSource $pastoralDataSource;

    /** @var PastoralTagMapper $pastoralTagMapper */
    private PastoralTagMapper $pastoralTagMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    private PastoralTypeMapper $pastoralTypeMapper;

    /** @var PastoralSubTypeMapper $pastoralSubTypeMapper */
    private PastoralSubTypeMapper $pastoralSubTypeMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    private PastoralSeverityMapper $pastoralSeverityMapper;

    /** @var PastoralReportMapper $pastoralReportMapper */
    private PastoralReportMapper $pastoralReportMapper;

    /** @var PastoralReportDashboardMapper $pastoralReportDashboardMapper */
    private PastoralReportDashboardMapper $pastoralReportDashboardMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    private PastoralGroupMapper $pastoralGroupMapper;

    /** @var HouseMapper $houseMapper */
    private HouseMapper $houseMapper;

    /** @var CampusMapper $campusMapper */
    private CampusMapper $campusMapper;


    /** @var Term\Mapper $termMapper */
    private Term\Mapper $termMapper;

    /**
     * @var YearLevel\Mapper
     */
    private YearLevel\Mapper $yearLevelMapper;

    /** @var ActionMapper $actionMapper */
    private ActionMapper $actionMapper;

    // Strings that can be converted to actual dates using strtotime
    private const THIS_YEAR = 'first day of january';
    private const THIS_TERM = 'this term'; // this is the only one that isn't a strtotime string
    private const PAST_6_MONTHS = '6 months ago';
    private const PAST_3_MONTHS = '3 months ago';
    private const PAST_MONTH = '1 month ago';
    private const PAST_FORTNIGHT = '14 days ago';
    private const PAST_WEEK = '7 days ago';
    private const TODAY = 'today';

    // Mappings of strings to labels that will be shown in the UI
    private const RELATIVE_DATE_PRESETS = [
        self::THIS_YEAR => 'This year',
        self::THIS_TERM => 'This term',
        self::PAST_6_MONTHS => 'Past 6 months',
        self::PAST_3_MONTHS => 'Past 3 months',
        self::PAST_MONTH => 'Past month',
        self::PAST_FORTNIGHT => 'Past fortnight',
        self::PAST_WEEK => 'Past week',
        self::TODAY => 'Today'
    ];

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
     */
    public function __construct(
        ErrorMapper $errorMapper,
        PastoralDataSource $pastoralDataSource,
        PastoralTagMapper $pastoralTagMapper,
        PastoralTypeMapper $pastoralTypeMapper,
        PastoralSubTypeMapper $pastoralSubTypeMapper,
        PastoralSeverityMapper $pastoralSeverityMapper,
        HouseMapper $houseMapper,
        CampusMapper $campusMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralReportMapper $pastoralReportMapper,
        PastoralReportDashboardMapper $pastoralReportDashboardMapper,
        Term\Mapper $termMapper,
        YearLevel\Mapper $yearLevelMapper,
        ActionMapper $actionMapper
    ) {
        $this->errorMapper                   = $errorMapper;
        $this->pastoralDataSource            = $pastoralDataSource;
        $this->pastoralTagMapper             = $pastoralTagMapper;
        $this->pastoralTypeMapper            = $pastoralTypeMapper;
        $this->pastoralSubTypeMapper         = $pastoralSubTypeMapper;
        $this->pastoralSeverityMapper        = $pastoralSeverityMapper;
        $this->houseMapper                   = $houseMapper;
        $this->campusMapper                  = $campusMapper;
        $this->pastoralGroupMapper           = $pastoralGroupMapper;
        $this->pastoralReportMapper          = $pastoralReportMapper;
        $this->pastoralReportDashboardMapper = $pastoralReportDashboardMapper;
        $this->termMapper                    = $termMapper;
        $this->yearLevelMapper               = $yearLevelMapper;
        $this->actionMapper                  = $actionMapper;
    }

    /**
     * Display report builder saved reports
     */
    public function getIndex(): void
    {
        $reportsAll = $this->pastoralReportMapper->findForUser($this->getUser());

        $reportsMine  = [];
        $reportsGroup = [];

        /** @var PastoralReport $reportCurrent */
        foreach ($reportsAll as $reportCurrent) {
            if ($reportCurrent->getOwner()->getId() == $this->getUser()->getId()) {
                $reportsMine[] = $reportCurrent;
            } else {
                if ($reportCurrent->hasAccess($this->getUser())) {
                    $reportsGroup[] = $reportCurrent;
                }
            }
        }

        // And render this thing out

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralReportBuilder/index.twig',
                'data'     => [
                    'user'         => $this->getUser(),
                    'reportsMine'  => $reportsMine,
                    'reportsGroup' => $reportsGroup,
                ],
                'title' => 'Pastoral Reports',
            ]
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

        foreach ($report->getStudents() as $student) {
            $year = $student->getYear() ? $student->getYear()->getName() : 'unknown';

            $studentsSelected[] = [
                'id'   => $student->getId(),
                'name' => "{$student->getFullName()} - Year {$year} {$student->getRole()->getName()} " .
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

        $orders = [
            'student',
            'date',
            'type',
            'subtype',
            'author',
            'points',
            'year',
            'house',
            'campus'
        ];

        // Only show severity if it is enabled by policy

        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            $orders[]   = 'severity';
            $severities = $this->pastoralSeverityMapper->findAll();
        }

        foreach ($orders as $orderCurrent) {
            if (!in_array($orderCurrent, $report->getOrder()->getAll())) {
                $report->getOrder()->add($orderCurrent);
            }
        }

        try {
            $this->pastoralReportDashboardMapper->findOneByColumnList([
                'user_id'   => $report->getOwner()->getId(),
                'report_id' => $report->getId(),
            ]);

            $report->setDashboard(true);
        } catch (MapperException $e) {
            $report->setDashboard(false);
        }

        // Default to using relative date
        $report->setUsesRelativeDate($report->getUsesRelativeDate() ?? true);
        $report->setRelativeDateString($report->getRelativeDateString() ?? self::THIS_YEAR);

        /* A stack of all YearLevel instances, ordered by sequence from the admin area
         */
        $yearLevels = $this->yearLevelMapper->findAll();

        // And render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@pastoralReportBuilder/modify.twig',
                'data'     => [
                    'moderator'        => $this->getUser()->getAccessList()->filterBy(
                        'service',
                        UserAccess::SERVICE_PASTORAL
                    )->count() > 0,
                    'years'            => $yearLevels,
                    'houses'           => $this->houseMapper->findAll()->sortBy('sequence', 'asc'),
                    'campuses'         => $this->campusMapper->findAll()->sortBy('sequence', 'asc'),
                    'types'            => $this->pastoralTypeMapper->findAll(),
                    'subTypes'         => $this->pastoralSubTypeMapper->findAll(),
                    'severities'       => $severities,
                    'tags'             => $this->pastoralTagMapper->findAll(),
                    'actions'          => $availableActions,
                    'groups'           => $this->pastoralGroupMapper->findAll(['order' => ['name' => 'asc']]),
                    'studentsSelected' => $studentsSelected,
                    'authorsSelected'  => $authorsSelected,
                    'tagsSelected'     => $tagsSelected,
                    'actionsSelected'  => $actionsSelected,
                    'report'           => $report,
                    'datePresets'      => self::RELATIVE_DATE_PRESETS,
                    'selectedRelativeDate' => $report->getRelativeDateString()
                ],
                'title' => 'Pastoral Report',
            ]
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

        // And spit this thing out

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralReportBuilder/report_tags.twig',
                'data'     => [
                    'tag'          => $tag,
                    'results'      => $records,
                    'showSeverity' => $this->getConfigLoader()->get('pastoral_severity'),
                ],
                'title'    => 'Pastoral Tag Report',
            ]
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

        $searchResults = [];

        // Get the record ID of each record displayed on the page to the user

        foreach ($records as $result) {
            $searchResults[] = $result->getId();
        }

        $auditRecord = $pastoralAuditMapper->createModel();
        $auditRecord->setUser($this->getUser());
        $auditRecord->setReportId($report->getId());
        $auditRecord->setSearchResult(implode(',', $searchResults));
        $pastoralAuditMapper->save($auditRecord);

        // And spit this thing out

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralReportBuilder/report_results.twig',
                'data'     => [
                    'results' => $records,
                    'report'  => $report,
                    'showSeverity' => $this->getConfigLoader()->get('pastoral_severity'),
                ],
                'title' => $report->getName(),
            ]
        );
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
        ];
        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $headings['Severity'] = 'Severity';
        }
        $headings = array_merge($headings, [
            'Tags'         => 'Tags',
            'Points'       => 'Points',
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
        return [
            'Pastoral ID'  => $record->getId(),
            'Student Name' => $record->getStudent()->getFullName(),
            'Student ID'   => $record->getStudent()->getId(),
            'House'        => $record->getStudent()->getSchoolHouse() ? $record->getStudent()->getSchoolHouse()->getHouse() : '',
            'Campus'       => implode(',', $record->getStudent()->getCampusCollection()->getPropertyValues('name')),
            'Year'         => $record->getStudent()->getYear() ? $record->getStudent()->getYear()->getName() : '',
            'Title'        => $record->getHeading(),
            'Type'         => $record->getType() ? $record->getType()->getName() : '',
            'Sub-Type'     => $record->getSubType() ? $record->getSubType()->getName() : '',
            'Tags'         => implode(',', $record->getTags()->getPropertyValues('name')),
            'Points'       => $record->getPoints() ?: '',
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

        $beforeDate = Alaress_Date_Format::now();
        // Special case for the current term relative date, which is not a strtotime-able thingy
        if ($report->getUsesRelativeDate()) {
            if ($report->getRelativeDateString() === self::THIS_TERM) {
                $afterDate = $this->termMapper->findForToday()->getStart();
            } else {
                $afterDate = Alaress_Date_Format::newFromTimestamp(strtotime($report->getRelativeDateString()));
            }
        } else {
            $beforeDate = $report->getBeforeDate();
            $afterDate  = $report->getAfterDate();
        }

        return $this->pastoralDataSource->getReportData(
            $this->getUser(),
            $beforeDate,
            $afterDate,
            $report->getSeverity(),
            $report->getTypes(),
            $report->getSubTypes(),
            $report->getAuthors(),
            $report->getStudents(),
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
            $report->getIncludeUncompletedActionsOnly()
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
     * Insert/update pastoral report
     * @throws MapperException
     * @throws RouteNotFound
     * @throws \Exception
     */
    public function postReportModify(): void
    {
        $post = $this->getRequest()->post;

        /* User must supply name, types, years and houses
         */
        $includeStudentsWithoutYear  = $post->getBool('includeStudentsWithoutYear', false);
        $includeStudentsWithoutHouse = $post->getBool('includeStudentsWithoutHouse', false);
        // For years and houses, either no-house/year or years/houses need to be selected
        if (
            !strlen(trim($post['name']))
            || !count($post['types'])
            || !($includeStudentsWithoutYear || count($post['years']))
            || !($includeStudentsWithoutHouse || count($post['houses']))
        ) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        if (empty($post['id'])) {
            $pastoralReport = $this->pastoralReportMapper->createModel();
        } else {
            $pastoralReport = $this->pastoralReportMapper->findByIdWithFallback($post['id']);
        }

        $pastoralReport->setOwner($this->getUser());
        $pastoralReport->setName($post['name']);
        $pastoralReport->setDescription($post['description'] ?? '');
        $pastoralReport->setOrderCsv($post['order']);
        $pastoralReport->setInvolved($post['involved'] ?? false);

        $pastoralReport->setSeverity($this->pastoralSeverityMapper->createModel($post['severity'] ?? null));

        if (!empty($post['date-option']) && $post['date-option'] === PastoralReport::ABSOLUTE_DATE) {
            $pastoralReport->setUsesRelativeDate(false);

            if (!empty($post['before'])) {
                $pastoralReport->setBeforeDate(Alaress_Date_Format::newLocalShort($post['before']));
            }

            if (!empty($post['after'])) {
                $pastoralReport->setAfterDate(Alaress_Date_Format::newLocalShort($post['after']));
            }
        } else {
            $pastoralReport->setUsesRelativeDate(true);
            $pastoralReport->setRelativeDateString(self::THIS_YEAR);

            // Update with POSTed relative date if it's valid
            $relativeDateString = $post['relative-dates-dropdown'];
            if (!empty($relativeDateString) && array_key_exists($relativeDateString, self::RELATIVE_DATE_PRESETS)) {
                $pastoralReport->setRelativeDateString($relativeDateString);
            }
        }


        if (is_array($post['types'])) {
            $pastoralReport->setTypesCsv(implode(',', $post['types']));
        } else {
            $pastoralReport->setTypesCsv('');
        }

        if (is_array($post['subtypes'] ?? false)) {
            $pastoralReport->setSubTypesCsv(implode(',', $post['subtypes']));
        } else {
            $pastoralReport->setSubTypesCsv('');
        }

        if (is_array($post['authors'] ?? false)) {
            $pastoralReport->setAuthorsCsv(implode(',', $post['authors']));
        } else {
            $pastoralReport->setAuthorsCsv('');
        }

        if (is_array($post['students'] ?? false)) {
            $pastoralReport->setStudentsCsv(implode(',', $post['students']));
        } else {
            $pastoralReport->setStudentsCsv('');
        }

        if (is_array($post['tags'] ?? false)) {
            $pastoralReport->setTagsCsv(implode(',', $post['tags']));
        } else {
            $pastoralReport->setTagsCsv('');
        }

        if (is_array($post['years'] ?? false)) {
            $pastoralReport->setYearsCsv(implode(',', $post['years']));
        } else {
            $pastoralReport->setYearsCsv('');
        }

        if (is_array($post['houses'] ?? false)) {
            $pastoralReport->setHousesCsv(implode(',', $post['houses']));
        } else {
            $pastoralReport->setHousesCsv('');
        }

        if (is_array($post['campuses'])) {
            $pastoralReport->setCampusesCsv(implode(',', $post['campuses']));
        } else {
            $pastoralReport->setCampusesCsv('');
        }

        $pastoralReport->setIncludeStudentsWithoutYear($includeStudentsWithoutYear);
        $pastoralReport->setIncludeStudentsWithoutHouse($includeStudentsWithoutHouse);

        if ($this->getConfigLoader()->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
            if (is_array($post['actions'] ?? false)) {
                $pastoralReport->setActionsCsv(implode(',', $post['actions']));
            } else {
                $pastoralReport->setActionsCsv('');
            }
            $pastoralReport->setIncludeRecordsWithoutAction($post->getBool('includeRecordsWithoutAction', false));
            $pastoralReport->setIncludeUncompletedActionsOnly($post->getBool('includeUncompletedActionsOnly', false));
        }

        // Save groups the report is shared with

        if (is_array($post['group'] ?? false)) {
            $groupsCollection = new Collection();

            foreach ($post['group'] as $groupId => $checked) {
                if ($checked) {
                    $groupsCollection->add($this->pastoralGroupMapper->createModel($groupId));
                }
            }

            $pastoralReport->setGroups($groupsCollection);
        }

        $this->pastoralReportMapper->save($pastoralReport);

        // Update display on pastoral dashboard status

        $this->updatePastoralDashboard($pastoralReport, isset($post['dashboard']));

        // Go back to the reports list

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoralReportBuilder.getIndex')
        );
    }

    /**
     * Update the display status of a report on the current users dashboard
     * @throws MapperException
     */
    public function postReportDashboardModify(): void
    {
        $post = $this->getRequest()->post;

        // Create bare minimum report object

        $report = $this->pastoralReportMapper->createModel($post['reportId']);

        $result = $this->updatePastoralDashboard($report, $post['display']);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $this->buildJsonOutput([
                'status' => 'OK',
                'result' => $result,
            ]),
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

            $pastoralReportDashboard = $this->pastoralReportDashboardMapper->findOneByColumnList([
                'user_id'   => $this->getUser()->getId(),
                'report_id' => $report->getId(),
            ]);

            // We found the report on the dashboard, check if we need to remove it

            if (!$display) {
                // Report was being displayed on dashboard, delete it

                $this->pastoralReportDashboardMapper->delete($pastoralReportDashboard);
                $result = 'Removed from dashboard';
            } else {
                $result = 'No action, already on dashboard';
            }
        } catch (MapperException $e) {
            // Report wasn't on the dashboard already, check if we need to add it

            if ($display) {
                // Add the report to the dashboard

                $pastoralReportDashboard = $this->pastoralReportDashboardMapper->createModel();
                $pastoralReportDashboard->setUser($this->getUser());
                $pastoralReportDashboard->setReport($report);
                $this->pastoralReportDashboardMapper->save($pastoralReportDashboard);
                $result = 'Added to dashboard';
            } else {
                $result = 'No action, already not on dashboard';
            }
        }

        return $result;
    }
}
