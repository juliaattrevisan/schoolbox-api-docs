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
        $this->taskDHelper->addSource('uxui', 'homepageTemplates');

    }
}
