<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\DataSource as PastoralDataSource;
use Schoolbox\Pastoral\Report\Dashboard\Mapper as PastoralReportDashboardMapper;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use HouseMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;
use Schoolbox\Assessment\Term;

class Controller extends Base
{
    /** @var ErrorMapper $errorMapper */
    private $errorMapper;

    /** @var PastoralDataSource $pastoralDataSource */
    private $pastoralDataSource;

    /** @var PastoralTagMapper $pastoralTagMapper */
    private $pastoralTagMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    private $pastoralTypeMapper;

    /** @var PastoralSubTypeMapper $pastoralSubTypeMapper */
    private $pastoralSubTypeMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    private $pastoralSeverityMapper;

    /** @var PastoralReportMapper $pastoralReportMapper */
    private $pastoralReportMapper;

    /** @var PastoralReportDashboardMapper $pastoralReportDashboardMapper */
    private $pastoralReportDashboardMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    private $pastoralGroupMapper;

    /** @var UserMapper $userMapper */
    private $userMapper;

    /** @var HouseMapper $houseMapper */
    private $houseMapper;

    /** @var Term\Mapper $termMapper */
    private $termMapper;

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
     * @param UserMapper                    $userMapper
     * @param HouseMapper                   $houseMapper
     * @param PastoralGroupMapper           $pastoralGroupMapper
     * @param PastoralReportMapper          $pastoralReportMapper
     * @param PastoralReportDashboardMapper $pastoralReportDashboardMapper
     * @param Term\Mapper                   $termMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        PastoralDataSource $pastoralDataSource,
        PastoralTagMapper $pastoralTagMapper,
        PastoralTypeMapper $pastoralTypeMapper,
        PastoralSubTypeMapper $pastoralSubTypeMapper,
        PastoralSeverityMapper $pastoralSeverityMapper,
        UserMapper $userMapper,
        HouseMapper $houseMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralReportMapper $pastoralReportMapper,
        PastoralReportDashboardMapper $pastoralReportDashboardMapper,
        Term\Mapper $termMapper
    ) {
        $this->errorMapper               = $errorMapper;
        $this->pastoralDataSource        = $pastoralDataSource;
        $this->pastoralTagMapper         = $pastoralTagMapper;
        $this->pastoralTypeMapper        = $pastoralTypeMapper;
        $this->pastoralSubTypeMapper     = $pastoralSubTypeMapper;
        $this->pastoralSeverityMapper    = $pastoralSeverityMapper;
        $this->userMapper                = $userMapper;
        $this->houseMapper               = $houseMapper;
        $this->pastoralGroupMapper       = $pastoralGroupMapper;
        $this->pastoralReportMapper          = $pastoralReportMapper;
        $this->pastoralReportDashboardMapper = $pastoralReportDashboardMapper;
        $this->termMapper                    = $termMapper;
    }

    /**
     * Display report builder saved reports
     */
    public function getIndex()
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
     */
    public function getReportInsert()
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
    public function getReportUpdate($report)
    {
        // These are required to populate the selectize

        // Students

        $studentsSelected = [];

        foreach ($report->getStudents() as $student) {
            $studentsSelected[] = [
                'id'   => $student->getId(),
                'name' => "{$student->getFullName()} - Year {$student->getYear()} {$student->getRole()->getName()} " .
                    "({$student->getExternalId()})",
            ];
        }

        // Authors

        $authorsSelected = [];

        foreach ($report->getAuthors() as $author) {
            $authorsSelected[] = [
                'id'   => $author->getId(),
                'name' => "{$author->getFullName()} - Year {$author->getYear()} {$author->getRole()->getName()} " .
                    "({$author->getExternalId()})",
            ];
        }

        // Authors

        $tagsSelected = [];

        foreach ($report->getTags() as $tag) {
            $tagsSelected[] = [
                'id'   => $tag->getId(),
                'name' => $tag->getName(),
            ];
        }

        $orders = [
            'student',
            'date',
            'type',
            'subtype',
            'author',
            'points',
            'year',
            'house'
        ];

        // Only show severity if it is enabled by policy

        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            $orders[]   = 'severity';
            $severities = $this->pastoralSeverityMapper->findAll()->sortBy('order', 'asc');
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

        // And render this thing out
        $this->getResponse()->content->set(
            [
                'template' => '@pastoralReportBuilder/modify.twig',
                'data'     => [
                    'moderator'        => $this->getUser()->getAccessList()->filterBy(
                            'service',
                            UserAccess::SERVICE_PASTORAL
                        )->count() > 0,
                    'years'            => $this->userMapper->getYearLevels(),
                    'houses'           => $this->houseMapper->findAll()->sortBy('house', 'asc'),
                    'types'            => $this->pastoralTypeMapper->findAll()->sortBy('name', 'asc'),
                    'subTypes'         => $this->pastoralSubTypeMapper->findAll()->sortBy('name', 'asc'),
                    'severities'       => $severities,
                    'tags'             => $this->pastoralTagMapper->findAll(),
                    'groups'           => $this->pastoralGroupMapper->findAll(['order' => ['name' => 'asc']]),
                    'studentsSelected' => $studentsSelected,
                    'authorsSelected'  => $authorsSelected,
                    'tagsSelected'     => $tagsSelected,
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
    public function getReportTags($tag)
    {
        $records = $this->pastoralDataSource->findByTag($this->getUser(), $tag);

        // And spit this thing out

        $this->getResponse()->content->set(
            [
                'template' => '@pastoralReportBuilder/report_tags.twig',
                'data'     => [
                    'tag'     => $tag,
                    'results' => $records,
                ],
                'title' => 'Pastoral Tag Report',
            ]
        );
    }

    /**
     * Generate a pastoral report with the relevant filtering applied, this version has the accordion and pagination
     *
     * @param PastoralReport $report
     */
    public function getReportResults($report)
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
                ],
                'title' => $report->getName(),
            ]
        );
    }

    /**
     * Get pastoral report records
     *
     * @param PastoralReport $report
     *
     * @return Collection
     */
    private function getReportData($report)
    {
        // Get matching records

        $beforeDate = Alaress_Date_Format::now();
        // Special case for the current term relative date, which is not a strtotime-able thingy
        if ($report->getRelativeDateString() === self::THIS_TERM) {
            $afterDate = $this->termMapper->findForToday()->getStart();
        } else {
            $afterDate = Alaress_Date_Format::newFromTimestamp(strtotime($report->getRelativeDateString()));
        }

        if (!$report->getUsesRelativeDate()) {
            $beforeDate = $report->getBeforeDate();
            $afterDate = $report->getAfterDate();
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
            $report->getHouses()
        );
    }

    /**
     * Delete pastoral report
     *
     * @param PastoralReport $report
     */
    public function postReportDelete($report)
    {
        $this->pastoralReportMapper->delete($report);
    }

    /**
     * Insert/update pastoral report
     */
    public function postReportModify()
    {
        $post = $this->getRequest()->post;

        /* User must supply name, types and years
         */
        if (!strlen(trim($post['name'])) || !count($post['types']) || !count($post['years'])) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_INFORMATION_MISSING);
        }

        /** @var PastoralReport $pastoralReport */

        if (empty($post['id'])) {
            $pastoralReport = $this->pastoralReportMapper->createModel();
        } else {
            $pastoralReport = $this->pastoralReportMapper->findByIdWithFallback($post['id']);
        }

        $pastoralReport->setOwner($this->getUser());
        $pastoralReport->setName($post['name']);
        $pastoralReport->setDescription($post['description']);
        $pastoralReport->setOrderCsv($post['order']);
        $pastoralReport->setInvolved($post['involved']);

        // Only save severity if it is enabled by policy

        if ($post['severity'] && $this->getConfigLoader()->get('pastoral_severity')) {
            $pastoralReport->setSeverity($this->pastoralSeverityMapper->createModel($post['severity']));
        }

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

        if (is_array($post['subtypes'])) {
            $pastoralReport->setSubTypesCsv(implode(',', $post['subtypes']));
        } else {
            $pastoralReport->setSubTypesCsv('');
        }

        if (is_array($post['authors'])) {
            $pastoralReport->setAuthorsCsv(implode(',', $post['authors']));
        } else {
            $pastoralReport->setAuthorsCsv('');
        }

        if (is_array($post['students'])) {
            $pastoralReport->setStudentsCsv(implode(',', $post['students']));
        } else {
            $pastoralReport->setStudentsCsv('');
        }

        if (is_array($post['tags'])) {
            $pastoralReport->setTagsCsv(implode(',', $post['tags']));
        } else {
            $pastoralReport->setTagsCsv('');
        }

        if (is_array($post['years'])) {
            $pastoralReport->setYearsCsv(implode(',', $post['years']));
        } else {
            $pastoralReport->setTypesCsv('');
        }

        if (is_array($post['houses'])) {
            $pastoralReport->setHousesCsv(implode(',', $post['houses']));
        } else {
            $pastoralReport->setHousesCsv('');
        }

        // Save groups the report is shared with

        if (is_array($post['group'])) {
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
     */
    public function postReportDashboardModify()
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
     */
    private function updatePastoralDashboard($report, $display)
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
