<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use External\Canvas\Importer\Codec\FolderCode;
use Psr\Log\LoggerInterface;
use Schoolbox\UserMapper;


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
     * @var UserMapper
     */
    private UserMapper $userMapper;


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
        $this->userMapper = \Schoolbox\UserMapper::getInstance();
        $this->newsMapper = \Schoolbox\Comms\News\Mapper::getInstance();
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
         * for all the Courses that we imported; contextCodes are stored in the canvas_id
         * column, but you'll need to prefix it with "course_" before you can use
         * them in the canvas api
         */
        $query = "
            SELECT 
                CONCAT('course_', canvas_id) AS contextCode, 
                name, 
                code
            FROM course_dim
        ";

        $statement = $this->canvasDb->build()->executeQuery($query);

        /* For each of the courses you found, figure out which folders are destinations
         * One would have to presume schoolbox folders exist, whose folder codes match
         * the course's code exactly
         */
        $courseCodes = [];
        $codec = new FolderCode();
        $currentYear = (int)date('Y');

        while ($row = $statement->fetch()) {
            /* We could have skipped this with a WHERE LIKE '%$currentYear' but we couldn't
             * log the skips and other diagnostic hankypanky
             */
            $folderCode = $codec->decode($currentYear, $row['code']);

            if (null === $folderCode) {
                $this->logDebug(sprintf(
                    'Course code %s is not a class, or does not occur in current year; skipping',
                    $row['code']
                ));

                continue;
            }

            /* Try to figure out where to make an article in schoolbox
             */
            try {
                $folder = $this->folderMapper->findByCode($folderCode);
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* If you can't find a matching folder for the course code provided, skip it
                 */
                $this->logDebug(sprintf(
                    'Course code %s does not have a corresponding schoolbox folder; skipping',
                    $row['code']
                ));

                continue;
            }

            /* Add it to the stack
             */
            $courseCodes[$row['contextCode']] = $folder;
        }

        /* No course codes found? No trabajo
         */
        if (!\count($courseCodes)) {
            $this->logInfo('No course codes found; exiting');
            return;
        }

        /* If we found any meaningful course codes,
         * fetch article contents from the api
         */
        $announcements = $this->api->retrieve('GET', 'api/v1/announcements', [
            'context_codes' => array_keys($courseCodes)
        ]);

        /* No announcements? No trabajo
         */
        if (!\count($announcements)) {
            $this->logInfo('No announcements found; exiting');
            return;
        }

        /* TODO: Define authors
         *  Canvas Importer doesn't create user accounts; they're presumed toe exist somehow
         *  but in the case where they don't exist in schoolbox.user, assume a default owner
         *  We don't really have anyone, so assign Schoolbox Admin arbitrarily...
         *  (There should be a non-human system user account that can be given this kind of data)
         */
        $defaultOwner = $this->userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);

        /* Every Announcement comes with a context_code field which you can use
         * to figure out the folder into which it should be placed, by using
         * $courseCodes as a hash lookup
         */
        foreach ($announcements as $announcement) {
            $folder = $courseCodes[$announcement['context_code']];

            /* Figure out the external id of the person who owns this
             * The external id should match someone in schoolbox.user (hopefully)
             * but if it can't, it'll fall back to $defaultOwner
             */
            $endpoint = sprintf(
                'api/v1/users/%s',
                $announcement['author']['id']
            );

            try {
                $announcementOwner = $this->api->retrieve('GET', $endpoint, []);

                $owner = $this->userMapper->findByExternalId($announcementOwner['sis_user_id']);
            } catch (\RuntimeException | \Schoolbox\Mapper\Exception $e) {
                /* noop: owner not found in schoolbox; assign default
                 */
            }

            $author = $owner ?? $defaultOwner;

            /* Spawn a new article instance, then map each Announcement's fields to the article's
             *
             * TODO: update if this announcement previously existed from a prior import
             *  instead of blindly spawning new instances every time, by checking a new
             *  relationship table comms_news_article_source (article_id, source_name, guid)
             *  A similar principle exists for schoolbox events
             */
            $article = $this->newsMapper->createModel()
                ->setTitle($announcement['title'])
                ->setBody($announcement['message'])
                ->setSticky(false)
                ->setAuthor($author);

            new \Alaress_Probe($article->getTitle(), $article->getBody(), $article->getAuthor()->getFullName());

            /* Make the folder an audiencing segment/brick for the article
             */

            /* Shank it into comms_news_article
             */

        }
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

    /**
     * If a logger was provided, bump out a message
     *
     * @param string $message
     * @return void
     */
    private function logInfo(string $message): void
    {
        if ($this->log) {
            $this->log->info($message);
        }
    }
}