<?php
/**
 * Controller for statistics
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Statistics;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\Folder\Model AS FolderModel;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\File\Mapper AS FileMapper;

class Controller extends Base
{
    /** @var DataSource $dataSource */
    private $dataSource;

    /**
     * @var Alaress_Date_Format $startDate
     */
    protected $startDate;

    /**
     * @var Alaress_Date_Format $endDate
     */
    protected $endDate;

    /**
     * @var string $roleFilter
     */
    protected $roleFilter;

    /**
     * Graph colours for each role type
     */
    const STAFF_COLOUR = '#579DCF';
    const STUDENT_COLOUR = '#E99D12';
    const PARENT_COLOUR = '#3DB114';
    const GUEST_COLOUR = '#762AAE';

    /**
     * Max number of items for each component
     */
    const MAX_TOP_VISITORS = 5;
    const MAX_TOP_FILES = 5;
    const MAX_RECENT_VISITORS = 8;
    const MAX_YET_TO_VISIT = 8;
    const MAX_TOP_LINKS = 5;

    /**
     * @var array $roles
     */
    private $roles = [
        RoleMapper::ROLE_STAFF,
        RoleMapper::ROLE_PARENT,
        RoleMapper::ROLE_STUDENT,
        RoleMapper::ROLE_GUEST
    ];

    /**
     * List of months & year for the date filter
     */
    private $filterDateOptions;


    /**
     * Controller constructor
     */
    public function __construct(DataSource $dataSource)
    {
        /**
         * Set DataSource
         */
        $this->setDataSource($dataSource);

        /**
         * Set the default start and end dates
         */
        $start = Alaress_Date_Format::now();
        $end = Alaress_Date_Format::now();
        $this->setStartDate($start->startOfMonth());
        $this->setEndDate($end->endOfMonth());

        /**
         * Set date options for the filter
         */
        $filterDateOptions = [];
        $dt = Alaress_Date_Format::now()->endOfDay();

        for ($i = 0; $i < 12; ++$i) {
            $filterDateOptions[$dt->getUsingFormat('Y-m')] = $dt->getUsingFormat('M, Y');
            $dt->addMonths(-1);
        }

        $this->filterDateOptions = $filterDateOptions;
    }


    /**
     * Receiving the filter form data & redirecting
     */
    public function getFilter()
    {
        $get = $this->getRequest()->query;
        $chunks = [
            'folderId' => $get['folderId']
        ];

        /**
         * If they've submitted this filter from a 'view all' page-
         * send them back to that page
         */
        if ($get['pageRoute'] != '') {
            $chunks[] = $get['pageRoute'];
        }

        /*
         * Both date filter params must be sent for the filter to go through
         */
        if (isset($get['startDate']) && isset($get['endDate'])) {
            $chunks['startDate'] = $get['startDate'];
            $chunks['endDate'] = $get['endDate'];

            /**
             * Role filter is not included if they select 'All'
             */
            if (isset($get['roleFilter'])) {
                $chunks['roleFilter'] = $get['roleFilter'];
            }
        }

        $redirect = $this->getRouteIfAuthorized('statistics.' .$get['pageRoute'], $chunks);
        $this->getResponse()->redirect->to($redirect);
    }


    /**
     * @api GET /statistics/homepage/{folderId}{/startDate,endDate,roleFilter}
     * @level 165
     *
     * @param FolderModel $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string $roleFilter
     */
    public function getIndex($folder, $startDate = null, $endDate = null, $roleFilter = null)
    {
        $dataSource = $this->getDataSource();

        if ($startDate !== null) {
            $this->setStartDate($startDate);
        }
        if ($endDate !== null) {
            $this->setEndDate($endDate);
        }
        if ($roleFilter !== null) {
            $this->setRoleFilter($roleFilter);
        }

        /**
         * Graph data
         */
        // list of epoch days included in this
        $start = (int)($this->getStartDate()->getUnixTimeStamp() / 86400);
        $end   = (int)($this->getEndDate()->getUnixTimeStamp() / 86400);

        // create a template array for populating into to account for zero days
        $data = array();
        foreach ($this->roles as $role) {
            for ($i = $start; $i <= $end; ++$i) {
                $data[$role][$i] = 0;
            }
        }

        $results = $dataSource->getVisits($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());

        foreach ($results as $result) {
            $time = (int)($result->getDate()->getUnixTimestamp() / 86400);
            $roleName = $result->getRole()->getType();

            if (isset($data[$roleName][$time])) {
                $data[$roleName][$time] += $result->getHitcount();
            } else {
                $data[$roleName][$time] = $result->getHitcount();
            }

            if (!isset($colours[$roleName])) {
                $const = strtoupper($roleName) . '_COLOUR';
                $colours[$roleName] = constant('self::'. $const);
            }
        }

        // Sort or remove the series, depending on whether or not they were populated
        foreach ($data as $group => &$d) {
            if (!isset($colours[$group])) {
                unset($data[$group]);
            }
            else {
                ksort($d);
            }
        }

        $colorList = [];
        $graphData = [];
        foreach ($data as $groupName => $series) {
            $groupData = [];
            $k = array_keys($series);
            for ($i = 0; $i < count($series); ++$i) {
                if ($series[$k[$i]] || $series[$k[$i+1]] || $series[$k[$i-1]]) {
                    $groupData[] = array(($k[$i]) * 86400000, (int)$series[$k[$i]]);
                }
            }

            $colorList[] = $colours[$groupName];
            $graphData[] = array(
                'name' => ucfirst($groupName),
                'data' => $groupData
            );
        }
        $colours = $colorList;

        // Give empty array if needed
        if (!count($graphData)) {
            $graphData[] = [];
        }


        /**
         * visitorCount is used for both top visitors and recent visitors
         * to determine whether to show 'view all' buttons
         */
        $visitorCount = $dataSource->getVisitorCount($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());


        /**
         * Top visitors
         */
        $topVisitors = $dataSource->getVisitors($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), 'hitcount DESC', self::MAX_TOP_VISITORS);

        $topVisitorsMore = false;
        if ($visitorCount > self::MAX_TOP_VISITORS) {
            $topVisitorsMore = true;
        }


        /**
         * Top files
         */
        $topFiles = $dataSource->getTopAccessed($folder->getId(), $dataSource::FILTER_FILE, $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), self::MAX_TOP_FILES, true);

        $topFileInfo = '';
        if (!empty($topFiles)) {
            $topFileInfo = array_values($topFiles)[0];
        }

        $topFilesMore = false;
        $topFilesAll = $dataSource->getTopAccessed($folder->getId(), $dataSource::FILTER_FILE, $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());

        if (count($topFilesAll) > self::MAX_TOP_FILES) {
            $topFilesMore = true;
        }


        /**
         * Recent visitors
         */
        $recentVisitors = $dataSource->getVisitors($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), 'datetime DESC', self::MAX_RECENT_VISITORS);

        $recentVisitorsMore = false;
        if ($visitorCount > self::MAX_RECENT_VISITORS) {
            $recentVisitorsMore = true;
        }


        /**
         * Yet to visit
         */
        $yetToVisitors = $dataSource->getYetToVisit($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), self::MAX_YET_TO_VISIT);

        $yetToVisitCount = $dataSource->getYetToVisitCount($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());
        if ($yetToVisitCount > self::MAX_YET_TO_VISIT) {
            $yetToVisitMore = true;
        }


        /**
         * Top links
         */
        $topLinks = $dataSource->getTopAccessed($folder->getId(), 'link', $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), self::MAX_TOP_FILES, true);

        $topLinkInfo = '';
        if (!empty($topLinks)) {
            $topLinkInfo = array_values($topLinks)[0];
        }

        $topLinksMore = false;
        $topLinksAll = $dataSource->getTopAccessed($folder->getId(), 'link', $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());

        if (count($topLinksAll) > self::MAX_TOP_LINKS) {
            $topLinksMore = true;
        }


        /**
         * Send view
         */
        $this->getResponse()->content->set(
            array(
                'template' => '@statistics/index.twig',
                'data' => [
                    'pageRoute' => 'getIndex',
                    'folder' => $folder,
                    'startDate' => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate' => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles' => $this->roles,
                    'roleFilter' => $roleFilter,

                    'graphColours' => $colours,
                    'graphXMin' => $this->getStartDate()->getUnixTimeStamp() * 1000,
                    'graphXMax' => $this->getEndDate()->getUnixTimeStamp() * 1000,
                    'graphData' => $graphData,

                    'maxTopVisitors' => self::MAX_TOP_VISITORS,
                    'topVisitors' => $topVisitors,
                    'topVisitorInfo' => $topVisitors->first(),
                    'topVisitorsMore' => $topVisitorsMore,

                    'topFiles' => $topFiles,
                    'topFileInfo' => $topFileInfo,
                    'topFilesMore' => $topFilesMore,

                    'recentVisitors' => $recentVisitors,
                    'recentVisitorsMore' => $recentVisitorsMore,

                    'yetToVisit' => $yetToVisitors,
                    'yetToVisitMore' => $yetToVisitMore,

                    'topLinks' => $topLinks,
                    'topLinkInfo' => $topLinkInfo,
                    'topLinksMore' => $topLinksMore,

                ],
            )
        );
    }


    /**
     * @api GET /statistics/homepage/{folderId}/topVisitors{/startDate,endDate,roleFilter}
     * @level 165
     *
     * @param FolderModel $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string $roleFilter
     */
    public function getTopVisitors($folder, $startDate = null, $endDate = null, $roleFilter = null)
    {
        $dataSource = $this->getDataSource();

        if ($startDate !== null) {
            $this->setStartDate($startDate);
        }
        if ($endDate !== null) {
            $this->setEndDate($endDate);
        }
        if ($roleFilter !== null) {
            $this->setRoleFilter($roleFilter);
        }

        $topVisitors = $dataSource->getVisitors($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), 'hitcount DESC');

        $this->getResponse()->content->set(
            array(
                'template' => '@statistics/topVisitors.twig',
                'data' => [
                    'pageRoute' => 'topVisitors',
                    'folder' => $folder,
                    'startDate' => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate' => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles' => $this->roles,
                    'roleFilter' => $roleFilter,
                    'topVisitors' => $topVisitors,
                    'topVisitorInfo' => $topVisitors->first(),
                ],
            )
        );
    }


    /**
     * @api GET /statistics/homepage/{folderId}/topFiles{/startDate,endDate,roleFilter}
     * @level 165
     *
     * @param FolderModel $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string $roleFilter
     */
    public function getTopFiles($folder, $startDate = null, $endDate = null, $roleFilter = null)
    {
        $dataSource = $this->getDataSource();

        if ($startDate !== null) {
            $this->setStartDate($startDate);
        }
        if ($endDate !== null) {
            $this->setEndDate($endDate);
        }
        if ($roleFilter !== null) {
            $this->setRoleFilter($roleFilter);
        }

        $topFiles = $dataSource->getTopAccessed($folder->getId(), $dataSource::FILTER_FILE, $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), null, true);

        $this->getResponse()->content->set(
            array(
                'template' => '@statistics/topFiles.twig',
                'data' => [
                    'pageRoute' => 'getTopFiles',
                    'folder' => $folder,
                    'startDate' => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate' => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles' => $this->roles,
                    'roleFilter' => $roleFilter,
                    'topFiles' => $topFiles
                ],
            )
        );
    }


    /**
     * @api GET /statistics/homepage/{folderId}/recentVisitors{/startDate,endDate,roleFilter}
     * @level 165
     *
     * @param FolderModel $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string $roleFilter
     */
    public function getRecentVisitors($folder, $startDate = null, $endDate = null, $roleFilter = null)
    {
        $dataSource = $this->getDataSource();

        if ($startDate !== null) {
            $this->setStartDate($startDate);
        }
        if ($endDate !== null) {
            $this->setEndDate($endDate);
        }
        if ($roleFilter !== null) {
            $this->setRoleFilter($roleFilter);
        }

        $recentVisitors = $dataSource->getVisitors($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), 'datetime DESC');

        $this->getResponse()->content->set(
            array(
                'template' => '@statistics/recentVisitors.twig',
                'data' => [
                    'pageRoute' => 'getTopVisitors',
                    'folder' => $folder,
                    'startDate' => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate' => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles' => $this->roles,
                    'roleFilter' => $roleFilter,
                    'recentVisitors' => $recentVisitors
                ],
            )
        );
    }


    /**
     * @api GET /statistics/homepage/{folderId}/yetToVisit{/startDate,endDate,roleFilter}
     * @level 165
     *
     * @param FolderModel $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string $roleFilter
     */
    public function getYetToVisit($folder, $startDate = null, $endDate = null, $roleFilter = null)
    {
        $dataSource = $this->getDataSource();

        if ($startDate !== null) {
            $this->setStartDate($startDate);
        }
        if ($endDate !== null) {
            $this->setEndDate($endDate);
        }
        if ($roleFilter !== null) {
            $this->setRoleFilter($roleFilter);
        }

        $yetToVisitors = $dataSource->getYetToVisit($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());

        $this->getResponse()->content->set(
            array(
                'template' => '@statistics/yetToVisit.twig',
                'data' => [
                    'pageRoute' => 'getYetToVisit',
                    'folder' => $folder,
                    'startDate' => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate' => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles' => $this->roles,
                    'roleFilter' => $roleFilter,
                    'yetToVisitors' => $yetToVisitors
                ],
            )
        );
    }


    /**
     * @api GET /statistics/homepage/{folderId}/topLinks{/startDate,endDate,roleFilter}
     * @level 165
     *
     * @param FolderModel $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string $roleFilter
     */
    public function getTopLinks($folder, $startDate = null, $endDate = null, $roleFilter = null)
    {
        $dataSource = $this->getDataSource();

        if ($startDate !== null) {
            $this->setStartDate($startDate);
        }
        if ($endDate !== null) {
            $this->setEndDate($endDate);
        }
        if ($roleFilter !== null) {
            $this->setRoleFilter($roleFilter);
        }

        $topLinks = $dataSource->getTopAccessed($folder->getId(), $dataSource::FILTER_LINK, $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), null, true);

        $this->getResponse()->content->set(
            array(
                'template' => '@statistics/topLinks.twig',
                'data' => [
                    'pageRoute' => 'getTopLinks',
                    'folder' => $folder,
                    'startDate' => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate' => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles' => $this->roles,
                    'roleFilter' => $roleFilter,
                    'topLinks' => $topLinks
                ],
            )
        );
    }


    /**
     * @param $dataSource
     */
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }

    /**
     * @return DataSource
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @param Alaress_Date_Format $startDate
     */
    protected function setStartDate(Alaress_Date_Format $startDate) {
        $this->startDate = $startDate;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getStartDate() {
        return $this->startDate;
    }

    /**
     * @param Alaress_Date_Format $endDate
     */
    protected function setEndDate(Alaress_Date_Format $endDate) {
        $this->endDate = $endDate;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getEndDate() {
        return $this->endDate;
    }

    /**
     * @param $roleFilter
     */
    protected function setRoleFilter($roleFilter) {
        $this->roleFilter = $roleFilter;
    }

    /**
     * @return mixed
     */
    protected function getRoleFilter() {
        return $this->roleFilter;
    }
}