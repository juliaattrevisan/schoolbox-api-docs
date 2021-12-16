<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use External\Canvas\Importer\Codec\FolderCode;
use Psr\Log\LoggerInterface;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\GenericCollection;
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
     * @var \Schoolbox\Comms\News\Mapper
     */
    private \Schoolbox\Comms\News\Mapper $newsMapper;


    /**
     * @var \Schoolbox\Comms\News\Audience\Mapper
     */
    private \Schoolbox\Comms\News\Audience\Mapper $audienceMapper;


    /**
     * @var StrategyFactory
     */
    private StrategyFactory $strategyFactory;


    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param CanvasDb                              $canvasDb
     * @param IApi                                  $api
     * @param \Schoolbox\Resource\Folder\Mapper     $folderMapper
     * @param UserMapper                            $userMapper
     * @param \Schoolbox\Comms\News\Mapper          $newsMapper
     * @param \Schoolbox\Comms\News\Audience\Mapper $audienceMapper
     * @param StrategyFactory                       $strategyFactory
     * @param LoggerInterface|null                  $log
     */
    public function __construct(
        CanvasDb $canvasDb,
        IApi $api,
        \Schoolbox\Resource\Folder\Mapper $folderMapper,
        \Schoolbox\UserMapper $userMapper,
        \Schoolbox\Comms\News\Mapper $newsMapper,
        \Schoolbox\Comms\News\Audience\Mapper $audienceMapper,
        StrategyFactory $strategyFactory,
        ?LoggerInterface $log = null
    ) {
        $this->canvasDb = $canvasDb;
        $this->api = $api;
        $this->log = $log;

        $this->folderMapper = $folderMapper;
        $this->userMapper = $userMapper;
        $this->newsMapper = $newsMapper;
        $this->audienceMapper = $audienceMapper;
        $this->strategyFactory = $strategyFactory;
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
        /* TODO: Define default author
         *  Canvas Importer doesn't create user accounts; they're presumed toe exist somehow
         *  but in the case where they don't exist in schoolbox.user, assume a default owner
         *  We don't really have anyone, so assign Schoolbox Admin arbitrarily...
         *  (There should be a non-human system user account that can be given this kind of data)
         */
        $defaultOwner = $this->userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);

        if (null === $defaultOwner) {
            $this->logInfo('Default owner not found; exiting');
            return;
        }

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

        $this->importAnnouncements($announcements, $courseCodes, $defaultOwner);
    }


    /**
     * Import $announcements into folders identified by $courseCodes
     * Assigns $defaultOwner as the author if the actual author can't be found in schoolbox.user
     *
     * @param array           $announcements
     * @param array           $courseCodes
     * @param \Schoolbox\User $defaultOwner
     * @return void
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DebugBar\DebugBarException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function importAnnouncements(array $announcements, array $courseCodes, \Schoolbox\User $defaultOwner): void
    {
        /* Every Announcement comes with a context_code field which you can use
         * to figure out the folder into which it should be placed, by using
         * $courseCodes as a hash lookup
         */
        foreach ($announcements as $announcement) {
            /* Skip announcements that aren't published
             */
            if (!(int)$announcement['published']) {
                $this->logDebug(sprintf(
                    'Announcement "%s" by %s is not published in canvas; skipping',
                    $announcement['title'],
                    $announcement['author']['display_name']
                ));

                continue;
            }

            $folder = $courseCodes[$announcement['context_code']];

            /* Figure out the external id of the person who owns this
             * The external id should match someone in schoolbox.user (hopefully)
             * but if it can't, it'll fall back to $defaultOwner
             */
            $endpoint = sprintf(
                'api/v1/users/%s',
                $announcement['author']['id']
            );

            $this->logDebug(sprintf(
                '%s announcement %s: searching for article author %s (id %s) in canvas endpoint %s',
                $announcement['context_code'],
                $announcement['id'],
                $announcement['author']['display_name'],
                $announcement['author']['id'],
                $endpoint
            ));

            /* Inspect canvas for this author
             */
            try {
                $announcementOwner = $this->api->retrieve('GET', $endpoint, []);
            } catch (\RuntimeException $e) {
                $this->logDebug(sprintf(
                    '%s announcement %s: endpoint %s error - %s; skipping',
                    $announcement['context_code'],
                    $announcement['id'],
                    $endpoint,
                    $e->getMessage()
                ));

                continue;
            }

            /* Inspect schoolbox for this owner
             */
            try {
                $owner = $this->userMapper->findByExternalId($announcementOwner['sis_user_id']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* noop: owner not found in schoolbox; assign default
                 */
                $owner = null;

                $this->logDebug(sprintf(
                    '%s announcement %s: author %s (externalId/sis_user_id %s) not found in schoolbox users - reverting to default ownership by %s',
                    $announcement['context_code'],
                    $announcement['id'],
                    $announcement['author']['display_name'],
                    $announcementOwner['sis_user_id'],
                    $defaultOwner->getFullName()
                ));
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
                ->setBlurb('')
                ->setBody($announcement['message'])
                ->setSticky(false)
                ->setAuthor($author)
                ->setAudience(new GenericCollection(\Schoolbox\Comms\News\Audience\Model::class));

            /* Assume some details about dates to map them to schoolbox concepts:
             *
             * Submitted === announcement[posted_at]
             * Accepted === announcement[posted_at]
             * Published === announcement[posted_at]
             * Expires === one year after announcement[posted_at]
             */
            $postedAt = \Alaress_Date_Format::newFromTimestamp(strtotime($announcement['posted_at']));
            $expiresAt = \Alaress_Date_Format::newFromTimestamp(strtotime($announcement['posted_at']))->addYears(1);

            $article
                ->setSubmittedAt($postedAt)
                ->setAcceptedAt($postedAt)
                ->setAcceptedBy($author)
                ->setPublishAt($postedAt)
                ->setExpiresAt($expiresAt);

            /* Shank it into comms_news_article
             */
            $this->newsMapper->save($article);

            /* Andy things. Not entirely sure if this was the intended manner in which
             * to persist audience segments, but whatever - it's a mess in there
             */
            $segment = (new \Schoolbox\Comms\News\Audience\Model())
                ->setEntityType(\Schoolbox\Comms\News\Audience\Segment\Strategy\Folder::getEntityType())
                ->setEntityValue((string)$folder->getId())
                ->setRelationship(\Schoolbox\Comms\News\Audience\Model::RELATIONSHIP_SELF)
                ->setCommsNewsArticle($article);

            /* Make the folder an audiencing segment/brick for the article
             */
            $audience = new GenericCollection(\Schoolbox\Comms\News\Audience\Model::class);
            $audience->add($segment);

            // Perform intelligent update and persistence in specialised helper class
            (new \Schoolbox\Comms\News\Audience\Segment\Transactor($this->audienceMapper, $this->strategyFactory))
                ->save($article, $audience);

            $this->logDebug(sprintf(
                '%s announcement %s: article titled "%s" has audiencing segment (%s, %s, %s)',
                $announcement['context_code'],
                $announcement['id'],
                $article->getTitle(),
                $segment->getEntityType(),
                $segment->getEntityValue(),
                $segment->getRelationship()
            ));

            $this->logInfo(sprintf(
                '%s announcement %s: created article "%s" by %s in folder %s',
                $announcement['context_code'],
                $announcement['id'],
                $article->getTitle(),
                $article->getAuthor()->getFullName(),
                $folder->getNameWithCodes()
            ));
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