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
use Schoolbox\Pastoral\Report\Mapper as PastoralReportMapper;
use Schoolbox\Pastoral\Report\Model as PastoralReport;
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
        PastoralReportMapper $pastoralReportMapper
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
    }

    /**
     * Display report builder saved reports
     */
    public function getIndex()
    {
        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoralReportBuilder/index.twig',
                'data' => array(
                    'user' => $this->getUser(),
                    'reports' => $this->pastoralReportMapper->findForUser($this->getUser())
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
            'author'
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
     * Generate a pastoral report with the relevant filtering applied, this version has the accordion and pagination
     *
     * @param PastoralReport $report
     */
    public function getReportResults($report)
    {
        $records = $this->getReportData($report);

        // Restrict results based on pagination

        if (!isset($query["pageCurrent"])) {
            $pageCurrent = 1;
        } else {
            $pageCurrent = (int)$query["pageCurrent"] == 0 ? 1 : (int)$query["pageCurrent"];
        }

        // This is the number of pages the user is allowed to select from at any one time (Must be even)
        // This is the number of pages the user is allowed to select from at any one time (Must be even)

        $pagesToDisplay = $this->getConfigLoader()->get('pastoral_page_visible');

        // This is the number of records before we force them to browse to another page

        $resultsPerPage = $this->getConfigLoader()->get('pastoral_page_size');
        $totalPages = ceil($records->count() / $resultsPerPage);

        // Put some limit checks on page numbers

        if ($pageCurrent < 1) {
            $pageCurrent = 1;
        } else {
            if ($pageCurrent > $totalPages) {
                $pageCurrent = $totalPages;
            }
        }

        // Grab the records we're going to render

        $paginatedRecords = new Collection;

        for ($i = ($pageCurrent - 1) * $resultsPerPage; $i < $pageCurrent * $resultsPerPage; $i++) {
            if ($records[$i] != null) {
                $paginatedRecords[] = $records[$i];
            }
        }

        // Keep an audit trail of any reports that are run

        $pastoralAuditMapper = PastoralAuditMapper::getInstance();

        $searchResults = [];

        // Get the record ID of each record displayed on the page to the user

        foreach ($paginatedRecords as $result) {
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
                    'results'           => $paginatedRecords,
                    'resultsPerPage'    => $resultsPerPage,
                    'pagesToDisplay'    => $pagesToDisplay % 2 == 0 ? $pagesToDisplay : $pagesToDisplay + 1,
                    'totalPages'        => $totalPages,
                    'totalResults'      => $records->count(),
                    'pageCurrent'       => $pageCurrent,
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

        $groupsCollection = new Collection();

        foreach ($post['group'] as $groupId => $checked) {
            if ($checked) {
                $groupsCollection->add($this->pastoralGroupMapper->createModel($groupId));
            }
        }

        $pastoralReport->setGroups($groupsCollection);

        $this->pastoralReportMapper->save($pastoralReport);

        // Go back to the reports list

        $this->getResponse()->redirect->afterPost(
            $this->getRouter()->generate('pastoralReportBuilder.getIndex')
        );
    }
}
