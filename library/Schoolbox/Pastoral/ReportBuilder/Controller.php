<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Pastoral\ReportBuilder;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Tag\Mapper as PastoralTagMapper;
use Schoolbox\Pastoral\Tag\Model as PastoralTag;
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
use Schoolbox\Pastoral\Report\Dashboard\Mapper as PastoralReportDashboardMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Group\Mapper as PastoralGroupMapper;
use Schoolbox\Pastoral\Group\Member\Mapper as PastoralGroupMemberMapper;
use Schoolbox\Pastoral\Audit\Mapper as PastoralAuditMapper;

class Controller extends Base
{
    /** @var ErrorMapper $errorMapper */
    private $errorMapper;

    /** @var PastoralMapper $pastoralMapper */
    private $pastoralMapper;

    /** @var PastoralTagMapper $pastoralTagMapper */
    private $pastoralTagMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    private $pastoralAccessMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    private $pastoralTypeMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    private $pastoralSeverityMapper;

    /** @var PastoralReportMapper $pastoralReportMapper */
    private $pastoralReportMapper;
    
    /** @var PastoralReportDashboardMapper $pastoralReportDashboardMapper */
    private $pastoralReportDashboardMapper;

    /** @var PastoralGroupMapper $pastoralGroupMapper */
    private $pastoralGroupMapper;

    /** @var PastoralGroupMemberMapper $pastoralGroupMemberMapper */
    private $pastoralGroupMemberMapper;

    /** @var UserMapper $userMapper */
    private $userMapper;

    /**
     * Constructor
     * @param ErrorMapper $errorMapper
     * @param PastoralMapper $pastoralMapper
     * @param PastoralAccessMapper $pastoralAccessMapper
     * @param PastoralTagMapper $pastoralTagMapper
     * @param PastoralTypeMapper $pastoralTypeMapper
     * @param PastoralSeverityMapper $pastoralSeverityMapper
     * @param UserMapper $userMapper
     * @param PastoralGroupMapper $pastoralGroupMapper
     * @param PastoralGroupMemberMapper $pastoralGroupMemberMapper
     * @param PastoralReportMapper $pastoralReportMapper
     * @param PastoralReportDashboardMapper $pastoralReportDashboardMapper
     */
    public function __construct(
        ErrorMapper $errorMapper,
        PastoralMapper $pastoralMapper,
        PastoralAccessMapper $pastoralAccessMapper,
        PastoralTagMapper $pastoralTagMapper,
        PastoralTypeMapper $pastoralTypeMapper,
        PastoralSeverityMapper $pastoralSeverityMapper,
        UserMapper $userMapper,
        PastoralGroupMapper $pastoralGroupMapper,
        PastoralGroupMemberMapper $pastoralGroupMemberMapper,
        PastoralReportMapper $pastoralReportMapper,
        PastoralReportDashboardMapper $pastoralReportDashboardMapper
    ) {
        $this->errorMapper = $errorMapper;
        $this->pastoralMapper = $pastoralMapper;
        $this->pastoralAccessMapper = $pastoralAccessMapper;
        $this->pastoralTagMapper = $pastoralTagMapper;
        $this->pastoralTypeMapper = $pastoralTypeMapper;
        $this->pastoralSeverityMapper = $pastoralSeverityMapper;
        $this->userMapper = $userMapper;
        $this->pastoralGroupMapper = $pastoralGroupMapper;
        $this->pastoralGroupMemberMapper = $pastoralGroupMemberMapper;
        $this->pastoralReportMapper = $pastoralReportMapper;
        $this->pastoralReportDashboardMapper = $pastoralReportDashboardMapper;
    }

    /**
     * Display report builder saved reports
     */
    public function getIndex()
    {
        $reportsAll = $this->pastoralReportMapper->findForUser($this->getUser());

        $reportsMine = [];
        $reportsGroup = [];

        /** @var \Schoolbox\Pastoral\Report\Model $reportCurrent */
        foreach ($reportsAll as $reportCurrent) {
            if ($reportCurrent->getOwner()->getId() == $this->getUser()->getId()) {
                $reportsMine[] = $reportCurrent;
            } else {
                $reportsGroup[] = $reportCurrent;
            }
        }

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoralReportBuilder/index.twig',
                'data' => array(
                    'user' => $this->getUser(),
                    'reportsMine' => $reportsMine,
                    'reportsGroup' => $reportsGroup
                ),
            )
        );
    }

    /**
     * Create new pastoral report
     */
    public function getReportInsert()
    {
        /** @var PastoralReport $report */
        $report = $this->pastoralReportMapper->createModel();
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
                'id'    => $student->getId(),
                'name'  => "{$student->getFullName()} - Year {$student->getYear()} {$student->getRole()->getName()} ({$student->getExternalId()})"
            ];
        }

        // Authors

        $authorsSelected = [];

        foreach ($report->getAuthors() as $author) {
            $authorsSelected[] = [
                'id'    => $author->getId(),
                'name'  => "{$author->getFullName()} - Year {$author->getYear()} {$author->getRole()->getName()} ({$author->getExternalId()})"
            ];
        }

        // Authors

        $tagsSelected = [];

        foreach ($report->getTags() as $tag) {
            $tagsSelected[] = [
                'id'    => $tag->getId(),
                'name'  => $tag->getName()
            ];
        }

        $orders = [
            'student',
            'date',
            'category',
            'author',
            'points',
            'year'
        ];

        // Only show severity if it is enabled by policy

        if (!$this->getConfigLoader()->get('pastoral_severity')) {
            $severities = [];
        } else {
            $orders[] = 'severity';
            $severities = $this->pastoralSeverityMapper->findAll()->sortBy('order', 'asc');
        }

        foreach ($orders as $orderCurrent) {
            if (!in_array($orderCurrent, $report->getOrder()->getAll())) {
                $report->getOrder()->add($orderCurrent);
            }
        }

        try {
            $this->pastoralReportDashboardMapper->findOneByColumnList([
                'user_id' => $report->getOwner()->getId(),
                'report_id' => $report->getId()
            ]);

            $report->setDashboard(true);
        } catch (MapperException $e) {
            $report->setDashboard(false);
        }

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoralReportBuilder/modify.twig',
                'data' => [
                    'moderator'             => $this->getUser()->getAccessList()->filterBy('service', UserAccess::SERVICE_PASTORAL),
                    'years'                 => $this->userMapper->getYearLevels(),
                    'types'                 => $this->pastoralTypeMapper->findAll()->sortBy('name', 'asc'),
                    'severities'            => $severities,
                    'tags'                  => $this->pastoralTagMapper->findAll(),
                    'groups'                => $this->pastoralGroupMapper->findAll(['order' => ['name' => 'asc']]),
                    'studentsSelected'      => $studentsSelected,
                    'authorsSelected'       => $authorsSelected,
                    'tagsSelected'          => $tagsSelected,
                    'report'                => $report
                ]
            )
        );
    }

    /**
     * Retrieve report of all pastoral records with the selected tag
     * @param PastoralTag $tag
     */
    public function getReportTags($tag)
    {
        $records = $this->pastoralMapper->findByTag($tag);

        // And spit this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoralReportBuilder/reportTags.twig',
                'data' => array(
                    'tag'               => $tag,
                    'results'           => $records
                ),
            )
        );

        // And spit this thing out

//        $this->getResponse()->content->set(
//            array(
//                'template' => '@pastoralReportBuilder/reportResults.twig',
//                'data' => array(
//                    'results'           => $paginatedRecords,
//                    'resultsPerPage'    => $resultsPerPage,
//                    'pagesToDisplay'    => $pagesToDisplay % 2 == 0 ? $pagesToDisplay : $pagesToDisplay + 1,
//                    'totalPages'        => $totalPages,
//                    'totalResults'      => $records->count(),
//                    'pageCurrent'       => $pageCurrent,
//                    'report'            => $report
//                ),
//            )
//        );
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
            array(
                'template' => '@pastoralReportBuilder/reportResults.twig',
                'data' => array(
                    'results'           => $records,
                    'report'            => $report
                ),
            )
        );
    }

    /**
     * Get pastoral report records
     *
     * @param PastoralReport $report
     * @return Collection
     */
    private function getReportData($report)
    {
        // Get matching records

        return $this->pastoralMapper->getReportData(
            $this->getUser(),
            $report->getBeforeDate(),
            $report->getAfterDate(),
            $report->getSeverity(),
            $report->getTypes(),
            $report->getAuthors(),
            $report->getStudents(),
            $report->getTags(),
            $report->getOrder(),
            $report->getInvolved(),
            $report->getYears()
        );
    }

    /**
     * Delete pastoral report
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

        if ($this->getConfigLoader()->get('pastoral_severity')) {
            $pastoralReport->setSeverity($post['severity']);
        }

        if (!empty($post['before'])) {
            $pastoralReport->setBeforeDate(Alaress_Date_Format::newLocalShort($post['before']));
        } else {
            $pastoralReport->setBeforeDate(null);
        }

        if (!empty($post['after'])) {
            $pastoralReport->setAfterDate(Alaress_Date_Format::newLocalShort($post['after']));
        } else {
            $pastoralReport->setAfterDate(null);
        }

        if (is_array($post['types'])) {
            $pastoralReport->setTypesCsv(implode(',', $post['types']));
        } else {
            $pastoralReport->setTypesCsv('');
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

        /** @var PastoralReport $report */
        $report = $this->pastoralReportMapper->createModel($post['reportId']);

        $result = $this->updatePastoralDashboard($report, $post['display']);
        
        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => $this->buildJsonOutput([
                'status' => 'OK',
                'result' => $result
            ])
        ]);
    }

    /**
     * Update pastoral dashboard record
     *
     * @param PastoralReport $report
     * @param bool $display
     * @return string
     */
    private function updatePastoralDashboard($report, $display)
    {
        try {

            // Check if the report is already on the dashboard

            $pastoralReportDashboard = $this->pastoralReportDashboardMapper->findOneByColumnList([
                'user_id' => $this->getUser()->getId(),
                'report_id' => $report->getId()
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
