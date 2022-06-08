<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;

class Controller extends Base
{
    private TaskD $taskDHelper;
    private Jwt $tokenBuilder;

    /**
     * Constructor
     *
     * @param Jwt            $tokenBuilder
     * @param IConfigLoader  $config
     * @param Router         $router
     * @param MessageFactory $messageFactory
     * @param Producer       $producer
     */
    public function __construct(
        Jwt $tokenBuilder,
        IConfigLoader $config,
        Router $router,
        MessageFactory $messageFactory,
        Producer $producer
    ) {
        $this->tokenBuilder = $tokenBuilder;

        $this->taskDHelper = new TaskD(
            $this->tokenBuilder,
            $config,
            $router,
            $messageFactory,
            $producer
        );
    }

    /**
     * base endpoint, append list of queries to run
     * this will build the urls, send to task-d to download the csv files
     * upload to storage, and notify the user.
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws \Interop\Queue\Exception\InvalidDestinationException
     * @throws \Interop\Queue\Exception\InvalidMessageException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getAudit()
    {
        //list of queries to run, if the request does not include one of these, error out.
        $validReports = ['assessmentAudit', 'uxui', 'user', 'lms', 'community'];
        $query = $this->getRequest()->query->get('report');
        if ('' === trim((string)$query)) {
            //if no value was handed, just assume the user was after everything
            $query = 'all';
        }

        //array of reports to be run
        $reports = explode(',', $query);
        //if query is 'all' lets add all options in
        if ('all' === $query) {
            $reports = $validReports;
        }
        //array of URLs to send to TaskD (this will be the URLs of the csv file downloads)
        $sources = [];
        //loop through all reports and build the sources / routes for all of them.
        foreach ($reports as $report) {
            if (!in_array($report, $validReports)) {
                throw new SchoolboxException("Report '{$report}' Does not exist.");
            }

            //the report name looks to be valid, lets add it to the list
            $this->$report();
        }

        //after looping through all reports, send them all to here...
        $this->taskDHelper->sendToTaskD('HealthCheck', $this->getUser());

        //redirect home
        $this->getResponse()->redirect->found('/');
    }

    /**
     * specific URL for assessmentAudit on folder 0
     */
    public function assessmentAudit()
    {
        //helper URL object
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
        $this->taskDHelper->addSource('assessmentAudit', 'assessmentAudit', $urlBase->setPath($this->getRouter()->generate('learning.getAssessmentAudit', ['folderId' => 0]))->toString());
    }

    /**
     * URL for getting the list of homepage templates, and returns a csv object
     */
    public function homepageTemplates()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("SELECT
                name AS 'Homepage Templates'
            FROM
                homepageTemplate
            ORDER BY
                name ASC; ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'HomepageTempates.csv');
    }

    /**
     * return list of reports for uxui
     */
    public function uxui()
    {
        $this->taskDHelper->addSource('uxui', 'topMenuReport');
        $this->taskDHelper->addSource('uxui', 'sideMenuReport');
        $this->taskDHelper->addSource('uxui', 'resourceBrowser');
        $this->taskDHelper->addSource('uxui', 'homepageTemplates');
    }

    /**
     * UXUI
     * Top Menu Report.csv
     */
    public function topMenuReport()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("SELECT
            mt.text as 'Icon Name',
            mt.title as 'Tooltip',
            mt.url as 'Destination URL',
            lc.name as 'Campus',
            (SELECT
                group_concat(g.name)
            FROM `groups` g
            LEFT JOIN
                menu_top_role mtr ON mtr.menu_top_id = mt.id
            WHERE
                mtr.role_id = g.id
            AND
                g.deleted_at IS NULL
            ) as 'Role'
        FROM
            menu_top mt
        LEFT JOIN
            menu_top_campus mtc on mtc.menu_top_id = mt.id
        LEFT JOIN
            luCampus lc on mtc.campus_id = lc.id
        ORDER BY
            sort_order asc; ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'TopMenuReport.csv');
    }

    /**
     * UXUI
     * Side Menu Report .csv
     */
    public function sideMenuReport()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            ms.title as 'Side Menu Item',
            ms.url as 'URL',
            lc.name as 'Campus',
            (SELECT
                group_concat(g.name)
            FROM
                `groups` g
            LEFT JOIN
                menu_side_role msr on msr.menu_side_id = ms.id
            WHERE
                msr.role_id = g.id
            AND
                g.deleted_at IS NULL
            ) AS 'Role'
        FROM
            menu_side ms
        LEFT JOIN
            menu_side_campus msc on msc.menu_side_id = ms.id
        LEFT JOIN
            luCampus lc on msc.campus_id = lc.id
        GROUP BY
            ms.title, ms.url
        ORDER BY
            ms.title, lc.name;
            ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'SideMenuReport.csv');
    }

    /**
     * UXUI
     * resourceBrowser .csv
     */
    public function resourceBrowser()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            f.name AS 'Folder/Page Name',
            f.id AS  'Folder/Page ID',
            IF(hc.template, 'homepage', 'folder') AS 'Page Status',
            (SELECT
                  IF(group_concat(g.name) IS NULL, '', group_concat(g.name))
              FROM
                 `groups` g
                     join
                 perms p on g.id = p.gid
              WHERE
                     p.type = 1
               AND
                 g.deleted_at is null
               AND f.id = p.cid
            ) as 'Read',
            (SELECT
                 IF(group_concat(g.name) IS NULL, '', group_concat(g.name))
              FROM
                 `groups` g
              JOIN
                 perms p on g.id = p.gid
              WHERE
                     p.type = 2
              AND
                 g.deleted_at is null
              AND f.id = p.cid
            ) AS 'Write'
        FROM
            folder f
        LEFT JOIN
            homepage_config hc ON f.id = hc.cid
        WHERE
                f.parent = 0
          AND
            f.deleted_at IS NULL;
            ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'ResourceBrowser.csv');
    }

    /**
     * return list of reports for user management audit
     */
    public function user()
    {
        $this->taskDHelper->addSource('user', 'listOfRoles');
        $this->taskDHelper->addSource('user', 'listOfCampuses');
    }

    /**
     * USER
     * listOfRoles.csv
     */
    public function listOfRoles()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            g.NAME   AS 'Role Name',
            Count(*) AS 'User Count'
        FROM
            groups g
        JOIN
            user u ON g.id = u.group_id
        WHERE
            u.deleted_at IS NULL
        AND
            g.deleted_at IS NULL
        GROUP BY
            g.NAME;
        ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfRoles.csv');
    }
    /**
     * USER
     * listOfCampuses.csv
     */
    public function listOfCampuses()
    {
        $database = FolderMapper::getInstance()->getDatabase();
        $statement = $database->prepare("
        SELECT
            c.NAME   AS 'Campus Name',
            Count(*) AS 'User Count'
        FROM
            luCampus c
        JOIN
            userCampus uc ON c.id = uc.campus
        JOIN
            user u ON uc.uid = u.id
        WHERE
            u.deleted_at IS NULL
        GROUP BY
            c.NAME;
        ");
        $statement->execute();
        $help = new Helper();
        $help->queryToCSV($statement, 'listOfCampuses.csv');
    }
}
