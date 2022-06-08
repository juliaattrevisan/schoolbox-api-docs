<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\AssessmentAudit;
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
     * @param Jwt $tokenBuilder
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
        $validReports = ['assessmentAudit', 'uxui'];
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
        $this->taskDHelper->addSource('uxui', 'TopMenuReport');
        $this->taskDHelper->addSource('uxui', 'SideMenuReport');

    }

    /**
     * UXUI
     * Top Menu Report.csv
     */
    public function TopMenuReport()
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
    public function SideMenuReport()
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
}
