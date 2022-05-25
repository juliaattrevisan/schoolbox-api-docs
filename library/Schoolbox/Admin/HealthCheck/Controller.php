<?php

namespace Schoolbox\Admin\HealthCheck;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Token\Jwt;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

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
        $validReports = ['all', 'assessmentExport'];
        $query = $this->getRequest()->query->get('report');
        if ('' === trim((string)$query)) {
            //if no value was handed, just assume the user was after everything
            $query = 'all';
        }
        $reports = explode(',', $query);

        foreach ($reports as $report) {
            if (!in_array($report, $validReports)) {
                throw new SchoolboxException("Report '{$report}' Does not exist.");
            }
        }

        //loop through all reports and build the sources / routes for all of them.

        //move this to separate functions for each 'report'
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();
        $reports = [];
        $reports[] = [
            'filename' => sprintf(
                "%s/%s.csv",
                'assessmentExport36',
                mb_substr('assessmentExport', 0, self::FILENAME_MAX_LENGTH, 'UTF-8'),
            ),
            'source'     => $urlBase->setPath('/learning/assessment-audit/0')->toString(),
        ];

        //after looping through all reports, send them all to here...
        $this->helper->sendToTaskD('HealthCheck', $reports, $this->getUser());

        //redirect home
        $this->getResponse()->redirect->found('/');
    }
}
