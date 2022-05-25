<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Learning\AssessmentAudit;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Controller extends Base
{
    /**
     * Max length for filenames: anything longer causes issues on upload.
     */
    private const FILENAME_MAX_LENGTH = 250;

    private Helper $helper;
    private Jwt $tokenBuilder;
    /**
     * Constructor
     *
     * @param Jwt $tokenBuilder
     */
    public function __construct(
        Jwt $tokenBuilder
    ) {
        $this->tokenBuilder = $tokenBuilder;
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
        $this->helper = new Helper(
            $this->tokenBuilder,
            $this->getConfigLoader(),
            $this->getRouter(),
            $this->getDi()->get('taskd.messageFactory'),
            $this->getDi()->get('taskd.messageProducer')
        );

        //list of queries to run, if the request does not include one of these, error out.
        $validReports = ['assessmentAudit'];
        $query = $this->getRequest()->query->get('report');
        if ('' === trim((string)$query)) {
            //if no value was handed, just assume the user was after everything
            $query = 'all';
        }
        //helper URL object
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
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

            $sources[] = [
                'filename' => sprintf(
                    "%s/%s.csv",
                    $report,
                    mb_substr($report, 0, self::FILENAME_MAX_LENGTH, 'UTF-8'),
                ),
                'source'     => $urlBase->setPath($this->router->generate('adminHealthCheckExport.' . $report))->toString(),
            ];
        }

        //after looping through all reports, send them all to here...
        $this->helper->sendToTaskD('HealthCheck', $sources, $this->getUser());

        //redirect home
        $this->getResponse()->redirect->found('/');
    }

    /**
     * specific URL for assessmentAudit on folder 0
     */
    public function assessmentAudit()
    {
        $folderMapper = FolderMapper::getInstance();
        $assessmentAudit = new AssessmentAudit($folderMapper, $this->getDi()->get('databaseInternal'), $this->getConfigLoader()->get('site_school_code'));
        $assessmentAudit->getAssessmentAudit($folderMapper->findById(0));
        exit(1);
    }
}
