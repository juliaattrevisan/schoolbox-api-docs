<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use Psr\Log\LoggerInterface;

/**
 * Import Canvas' Course Announcements as Schoolbox News Articles
 */
class Announcements
{

    /**
     * @var CanvasDb
     */
    private CanvasDb $canvasDb;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param CanvasDb             $canvasDb
     * @param IApi                 $api
     * @param LoggerInterface|null $log
     */
    public function __construct(CanvasDb $canvasDb, IApi $api, ?LoggerInterface $log = null)
    {
        $this->canvasDb = $canvasDb;
        $this->api = $api;
        $this->log = $log;

        /* TODO Inject these all later
         */
        $this->folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();
    }


    /**
     * Import the Announcements for each canvas Course,
     * saving them in schoolbox as news articles,
     * logging as we go
     *
     * @return void
     * @throws \Alaress\Database\Exception
     */
    public function import(): void
    {
        /* Use the internal db to interrogate canvas_data_<instanceId>.course_dim
         * for all the Courses that we imported; courseIds are stored in the canvas_id
         * column, but you'll need to prefix it with "course_" before you can use
         * them in the canvas api
         */
        $query = "
            SELECT 
                CONCAT('course_', canvas_id) AS courseId, 
                name, 
                code
            FROM course_dim 
        ";

        $statement = $this->canvasDb->build()->executeQuery($query);

        /* For each of the courses you found, figure out which folders are destinations
         * One would have to presume schoolbox folders exist, whose folder codes match
         * the course's code exactly
         */
        while ($row = $statement->fetch()) {
            try {
                $folder = $this->folderMapper->findByCode($row['code']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* If you can't find a matching folder for the course code provided, skip it
                 */
                $this->logDebug(sprintf(
                    'Course code %s does not have a corresponding schoolbox folder; skipping',
                    $row['code']
                ));

                continue;
            }

            /* Spawn a new article instance, then map each Announcement's fields to the article's
             */


        }



        /* Make the folder an audiencing segment/brick for the article
         */

        /* Shank it into comms_news_article
         */
    }


    /**
     * If a logger was provided, bump out a message
     *
     * @param string $message
     * @return void
     */
    private function logDebug(string $message): void
    {
        if ($this->log) {
            $this->log->debug($message);
        }
    }
}