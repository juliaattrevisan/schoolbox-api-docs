<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;

/**
 * Import Canvas' Course Announcements as Schoolbox News Articles
 */
class Announcements
{

    /**
     * @var IConnector
     */
    private IConnector $canvasDb;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param IConnector      $canvasDb
     * @param IApi            $api
     * @param LoggerInterface $log
     */
    public function __construct(IConnector $canvasDb, IApi $api, LoggerInterface $log)
    {
        $this->canvasDb = $canvasDb;
        $this->api = $api;
        $this->log = $log;
    }


    /**
     * Import the Announcements for each canvas Course,
     * saving them in schoolbox as news articles,
     * logging as we go
     *
     * @return void
     */
    public function import(): void
    {
        /* Use the internal db to interrogate canvas_data_<instanceId>.course_dim
         * for all the Courses that we imported; courseIds are stored in the canvas_id
         * column, but you'll need to prefix it with "course_" before you can use
         * them in the canvas api
         */

        /* For each of the courses you found, figure out which folders are destinations
         * One would have to presume schoolbox folders exist, whose folder codes match
         * the course's code exactly
         */

        /* If you can't find a matching folder for the course code provided, skip it
         */

        /* Spawn a new article instance, then map each Announcement's fields to the article's
         */

        /* Make the folder an audiencing segment/brick for the article
         */

        /* Shank it into comms_news_article
         */
    }
}