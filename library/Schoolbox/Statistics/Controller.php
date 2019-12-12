<?php
/**
 * Controller for statistics
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Statistics;

use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\Role;

class Controller extends Base
{

    /**
     * @var DataSource $dataSource
     */
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
    private const STAFF_COLOUR   = '#579DCF';
    private const STUDENT_COLOUR = '#E99D12';
    private const PARENT_COLOUR  = '#3DB114';
    private const GUEST_COLOUR   = '#762AAE';


    /**
     * Max number of items for each component
     */
    private const MAX_TOP_VISITORS    = 5;
    private const MAX_TOP_FILES       = 5;
    private const MAX_RECENT_VISITORS = 8;
    private const MAX_YET_TO_VISIT    = 8;
    private const MAX_TOP_LINKS       = 5;


    /**
     * A list of role types.
     *
     * @var array $roleTypes
     */
    private $roleTypes;


    /**
     * List of months & year for the date filter
     */
    private $filterDateOptions;


    /**
     * Controller constructor
     *
     * @param DataSource $dataSource
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
        $end   = Alaress_Date_Format::now();
        $this->setStartDate($start->startOfMonth());
        $this->setEndDate($end->endOfMonth());

        /**
         * Set date options for the filter
         */
        $filterDateOptions = [];
        $dt                = Alaress_Date_Format::now()->endOfDay();

        for ($i = 0; $i < 12; ++$i) {
            $filterDateOptions[$dt->getUsingFormat('Y-m')] = $dt->getUsingFormat('M, Y');
            $dt->addMonths(-1);
        }

        $this->filterDateOptions = $filterDateOptions;

        /* A stack known role types
         */
        $this->roleTypes = Role::getRoleTypes();
    }


    /**
     * Receiving the filter form data & redirecting
     */
    public function getFilter()
    {
        $get    = $this->getRequest()->query;
        $chunks = [
            'folderId' => $get['folderId'],
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
            $chunks['endDate']   = $get['endDate'];

            /**
             * Role filter is not included if they select 'All'
             */
            if (isset($get['roleFilter'])) {
                $chunks['roleFilter'] = $get['roleFilter'];
            }
        }

        $redirect = $this->getRouteIfAuthorized('statistics.' . $get['pageRoute'], $chunks);
        $this->getResponse()->redirect->to($redirect);
    }


    /**
     * @api   GET /statistics/homepage/{folderId}{/startDate,endDate,roleFilter}
     * @since 165
     *
     * @param FolderModel         $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string              $roleFilter
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
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


        [$colours, $graphData] = $this->compileVisitorGraphData($folder, $dataSource);


        /**
         * visitorCount is used for both top visitors and recent visitors
         * to determine whether to show 'view all' buttons
         */
        $visitorCount = $dataSource->getVisitorCount($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());


        /**
         * Top visitors
         */
        [$topVisitors, $topVisitorsMore] = $this->compileTopVisitorsData($folder, $dataSource, $visitorCount);


        /**
         * Top files
         */
        [$topFiles, $topFileInfo, $topFilesMore] = $this->compileTopFilesData($folder, $dataSource);


        /**
         * Recent visitors
         */
        [$recentVisitors, $recentVisitorsMore] = $this->compileRecentVisitorsData($folder, $dataSource, $visitorCount);


        /**
         * Yet to visit
         */
        [$yetToVisitors, $yetToVisitMore] = $this->compileNotYetVisitedData($folder, $dataSource);


        /**
         * Top links
         */
        [$topLinks, $topLinkInfo, $topLinksMore] = $this->compileTopLinksData($folder, $dataSource);


        /**
         * Send view
         */
        $this->getResponse()->content->set(
            [
                'template' => '@statistics/index.twig',
                'data'     => [
                    'pageRoute'         => 'getIndex',
                    'folder'            => $folder,
                    'startDate'         => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate'           => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles'             => $this->roleTypes,
                    'roleFilter'        => $roleFilter,

                    'graphColours' => $colours,
                    'graphXMin'    => $this->getStartDate()->getUnixTimeStamp() * 1000,
                    'graphXMax'    => $this->getEndDate()->getUnixTimeStamp() * 1000,
                    'graphData'    => $graphData,

                    'maxTopVisitors'  => self::MAX_TOP_VISITORS,
                    'topVisitors'     => $topVisitors,
                    'topVisitorInfo'  => $topVisitors->first(),
                    'topVisitorsMore' => $topVisitorsMore,

                    'topFiles'     => $topFiles,
                    'topFileInfo'  => $topFileInfo,
                    'topFilesMore' => $topFilesMore,

                    'recentVisitors'     => $recentVisitors,
                    'recentVisitorsMore' => $recentVisitorsMore,

                    'yetToVisit'     => $yetToVisitors,
                    'yetToVisitMore' => $yetToVisitMore,

                    'topLinks'     => $topLinks,
                    'topLinkInfo'  => $topLinkInfo,
                    'topLinksMore' => $topLinksMore,

                ],
            ]
        );
    }


    /**
     * @api   GET /statistics/homepage/{folderId}/topVisitors{/startDate,endDate,roleFilter}
     * @since 165
     *
     * @param FolderModel         $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string              $roleFilter
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
            [
                'template' => '@statistics/topVisitors.twig',
                'data'     => [
                    'pageRoute'         => 'getTopVisitors',
                    'folder'            => $folder,
                    'startDate'         => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate'           => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles'             => $this->roleTypes,
                    'roleFilter'        => $roleFilter,
                    'topVisitors'       => $topVisitors,
                    'topVisitorInfo'    => $topVisitors->first(),
                ],
            ]
        );
    }


    /**
     * @api   GET /statistics/homepage/{folderId}/topFiles{/startDate,endDate,roleFilter}
     * @since 165
     *
     * @param FolderModel         $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string              $roleFilter
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
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
            [
                'template' => '@statistics/topFiles.twig',
                'data'     => [
                    'pageRoute'         => 'getTopFiles',
                    'folder'            => $folder,
                    'startDate'         => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate'           => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles'             => $this->roleTypes,
                    'roleFilter'        => $roleFilter,
                    'topFiles'          => $topFiles,
                ],
            ]
        );
    }


    /**
     * @api   GET /statistics/homepage/{folderId}/recentVisitors{/startDate,endDate,roleFilter}
     * @since 165
     *
     * @param FolderModel         $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string              $roleFilter
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
            [
                'template' => '@statistics/recentVisitors.twig',
                'data'     => [
                    'pageRoute'         => 'getTopVisitors',
                    'folder'            => $folder,
                    'startDate'         => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate'           => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles'             => $this->roleTypes,
                    'roleFilter'        => $roleFilter,
                    'recentVisitors'    => $recentVisitors,
                ],
            ]
        );
    }


    /**
     * @api   GET /statistics/homepage/{folderId}/yetToVisit{/startDate,endDate,roleFilter}
     * @since 165
     *
     * @param FolderModel         $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string              $roleFilter
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
            [
                'template' => '@statistics/yetToVisit.twig',
                'data'     => [
                    'pageRoute'         => 'getYetToVisit',
                    'folder'            => $folder,
                    'startDate'         => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate'           => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles'             => $this->roleTypes,
                    'roleFilter'        => $roleFilter,
                    'yetToVisitors'     => $yetToVisitors,
                ],
            ]
        );
    }


    /**
     * @api   GET /statistics/homepage/{folderId}/topLinks{/startDate,endDate,roleFilter}
     * @since 165
     *
     * @param FolderModel         $folder
     * @param Alaress_Date_Format $startDate
     * @param Alaress_Date_Format $endDate
     * @param string              $roleFilter
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
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
            [
                'template' => '@statistics/topLinks.twig',
                'data'     => [
                    'pageRoute'         => 'getTopLinks',
                    'folder'            => $folder,
                    'startDate'         => $this->getStartDate()->getYear() . '-' . $this->getStartDate()->getMonth(1),
                    'endDate'           => $this->getEndDate()->getYear() . '-' . $this->getEndDate()->getMonth(1),
                    'filterDateOptions' => $this->filterDateOptions,
                    'roles'             => $this->roleTypes,
                    'roleFilter'        => $roleFilter,
                    'topLinks'          => $topLinks,
                ],
            ]
        );
    }


    /**
     * Compile the visitor data graph.
     *
     * @param FolderModel $folder
     * @param DataSource  $dataSource
     * @return array [$colorList, $graphData]
     *               $colorList: The colors for each role's line in `$graphData`
     *               $graphData: The points for each role's line:
     *                           [$timestamp, $visitorCount]
     */
    private function compileVisitorGraphData(FolderModel $folder, DataSource $dataSource): array
    {
        // create a template array for populating into to account for zero days
        $data    = [];
        $endDate = (clone $this->getEndDate())->endOfDay();
        foreach ($this->roleTypes as $roleType) {
            $runningDate = (clone $this->getStartDate())->startOfDay();
            while ($runningDate->lt($endDate)) {
                $data[$roleType][$runningDate->getDatabaseDate()] = 0;
                $runningDate                                      = $runningDate->addDays(1);
            }
        }

        $results = $dataSource->getVisits($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());

        // Compile a series of hit counts for each role, as well as their colors
        $colours = [];
        foreach ($results as $result) {
            $time     = $result->getDate()->getDatabaseDate();
            $roleName = $result->getRole()->getType();

            if (isset($data[$roleName][$time])) {
                $data[$roleName][$time] += $result->getHitcount();
            } else {
                $data[$roleName][$time] = $result->getHitcount();
            }

            if (!isset($colours[$roleName])) {
                switch ($roleName) {
                    case Role::STAFF:
                        $colours[$roleName] = self::STAFF_COLOUR;
                        break;

                    case Role::STUDENT:
                        $colours[$roleName] = self::STUDENT_COLOUR;
                        break;

                    case Role::PARENT:
                        $colours[$roleName] = self::PARENT_COLOUR;
                        break;

                    case Role::GUEST:
                    default:
                        $colours[$roleName] = self::GUEST_COLOUR;
                        break;
                }
            }
        }

        // Sort or remove each series from $data,
        // depending on whether or not they were populated
        foreach ($data as $group => &$d) {
            if (!isset($colours[$group])) {
                unset($data[$group]);
            } else {
                ksort($d);
            }
        }
        unset($d);

        // Build the graph dataset
        $colorList = [];
        $graphData = [];
        foreach ($data as $roleType => $series) {
            $groupData = [];
            $dates     = array_keys($series);
            foreach ($dates as $i => $currentDate) {
                $addRoleTypeData = false;
                $currentCount    = (int)($series[$currentDate] ?? 0);
                if ($currentCount > 0) {
                    $addRoleTypeData = true;
                } else {
                    $prevDate  = $dates[$i - 1] ?? null;
                    $nextDate  = $dates[$i + 1] ?? null;
                    $prevCount = $prevDate ? $series[$prevDate] ?? 0 : 0;
                    $nextCount = $nextDate ? $series[$nextDate] ?? 0 : 0;
                    if ($prevCount || $nextCount) {
                        $addRoleTypeData = true;
                    }
                }
                if ($addRoleTypeData) {
                    $groupData[] = [strtotime($dates[$i] . ' 00:00:00Z') * 1000, $currentCount];
                }
            }

            $colorList[] = $colours[$roleType];
            $graphData[] = [
                'name' => ucfirst($roleType),
                'data' => $groupData,
            ];
        }
        return [$colorList, $graphData];
    }


    /**
     * Compile data for top visitors.
     *
     * @param FolderModel $folder
     * @param DataSource  $dataSource
     * @param int         $visitorCount
     * @return array [$topVisitors, $topVisitorsMore]
     *               $topVisitors:     a list of `self::MAX_TOP_VISITORS` top visitors
     *               $topVisitorsMore: are there more visitors?
     */
    private function compileTopVisitorsData(FolderModel $folder, DataSource $dataSource, int $visitorCount): array
    {
        $topVisitors = $dataSource->getVisitors($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), 'hitcount DESC', self::MAX_TOP_VISITORS);

        $topVisitorsMore = false;
        if ($visitorCount > self::MAX_TOP_VISITORS) {
            $topVisitorsMore = true;
        }
        return [$topVisitors, $topVisitorsMore];
    }


    /**
     * Compile data for top files accessed.
     *
     * @param FolderModel $folder
     * @param DataSource  $dataSource
     * @return array [$topFiles, $topFileInfo, $topFilesMore]
     *               $topFiles:     a list of `self::MAX_TOP_FILES` files accessed
     *               $topFileInfo:  info about the most accessed file
     *               $topFilesMore: are there more files?
     */
    private function compileTopFilesData(FolderModel $folder, DataSource $dataSource): array
    {
        $topFiles    = $dataSource->getTopAccessed($folder->getId(), $dataSource::FILTER_FILE, $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), self::MAX_TOP_FILES, true);
        $topFileInfo = '';
        if (!empty($topFiles)) {
            $topFileInfo = array_values($topFiles)[0];
        }

        $topFilesAll = $dataSource->getTopAccessed($folder->getId(), $dataSource::FILTER_FILE, $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());
        return [$topFiles, $topFileInfo, count($topFilesAll) > self::MAX_TOP_FILES];
    }


    /**
     * Compile data for recent visitors.
     *
     * @param FolderModel $folder
     * @param DataSource  $dataSource
     * @param int         $visitorCount
     * @return array [$recentVisitors, $recentVisitorsMore]
     *               $recentVisitors:     a list of `self::MAX_RECENT_VISITORS` recent visitors
     *               $recentVisitorsMore: are there more visitors?
     */
    private function compileRecentVisitorsData(FolderModel $folder, DataSource $dataSource, int $visitorCount): array
    {
        $recentVisitors = $dataSource->getVisitors($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), 'datetime DESC', self::MAX_RECENT_VISITORS);

        $recentVisitorsMore = false;
        if ($visitorCount > self::MAX_RECENT_VISITORS) {
            $recentVisitorsMore = true;
        }
        return [$recentVisitors, $recentVisitorsMore];
    }


    /**
     * Compile data for yet-to-visit users.
     *
     * @param FolderModel $folder
     * @param DataSource  $dataSource
     * @return array [$yetToVisitors, $yetToVisitMore]
     *               $yetToVisitors:  a list of `self::MAX_YET_TO_VISIT` yet to visit
     *               $yetToVisitMore: are there more yet to visit?
     */
    private function compileNotYetVisitedData(FolderModel $folder, DataSource $dataSource): array
    {
        $yetToVisitors = $dataSource->getYetToVisit($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), self::MAX_YET_TO_VISIT);

        $yetToVisitMore  = false;
        $yetToVisitCount = $dataSource->getYetToVisitCount($folder->getId(), $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());
        if ($yetToVisitCount > self::MAX_YET_TO_VISIT) {
            $yetToVisitMore = true;
        }
        return [$yetToVisitors, $yetToVisitMore];
    }


    /**
     * Compile data for top links.
     *
     * @param FolderModel $folder
     * @param DataSource  $dataSource
     * @return array [$topLinks, $topLinkInfo, $topLinksMore]
     *               $topLinks:     a list of `self::MAX_TOP_LINKS` links accessed
     *               $topLinkInfo:  info about the most accessed link
     *               $topLinksMore: are there more links?
     */
    private function compileTopLinksData(FolderModel $folder, DataSource $dataSource): array
    {
        $topLinks = $dataSource->getTopAccessed($folder->getId(), 'link', $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter(), self::MAX_TOP_FILES, true);

        $topLinkInfo = '';
        if (!empty($topLinks)) {
            $topLinkInfo = array_values($topLinks)[0];
        }

        $topLinksAll = $dataSource->getTopAccessed($folder->getId(), 'link', $this->getStartDate(), $this->getEndDate(), $this->getRoleFilter());
        return [$topLinks, $topLinkInfo, count($topLinksAll) > self::MAX_TOP_LINKS];
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
    protected function setStartDate(Alaress_Date_Format $startDate)
    {
        $this->startDate = $startDate;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param Alaress_Date_Format $endDate
     */
    protected function setEndDate(Alaress_Date_Format $endDate)
    {
        $this->endDate = $endDate;
    }

    /**
     * @return Alaress_Date_Format
     */
    protected function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param $roleFilter
     */
    protected function setRoleFilter($roleFilter)
    {
        $this->roleFilter = $roleFilter;
    }

    /**
     * @return mixed
     */
    protected function getRoleFilter()
    {
        return $this->roleFilter;
    }
}
