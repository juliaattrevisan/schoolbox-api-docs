<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use External\Canvas\Importer\Cli\AnnouncementsTool;
use External\Canvas\Importer\Codec\FolderCode;
use Psr\Log\LoggerInterface;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\ValidDateOffset;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\LockInterface;

/**
 * Import Canvas' Course Announcements as Schoolbox News Articles
 */
class Announcements
{
    /**
     * Arbitrary string indicating this article came from the canvas importer
     */
    private const SOURCE_NAME = 'canvas_importer';


    /**
     * @var CanvasDb
     */
    private CanvasDb $canvasDb;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var IConfigLoader
     */
    private IConfigLoader $configLoader;


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
     * @var \Schoolbox\Comms\News\Source\Mapper
     */
    private \Schoolbox\Comms\News\Source\Mapper $sourceMapper;


    /**
     * @var \Schoolbox\Comms\News\Topic\Mapper
     */
    private \Schoolbox\Comms\News\Topic\Mapper $topicMapper;


    /**
     * @var StrategyFactory
     */
    private StrategyFactory $strategyFactory;


    /**
     * @var int
     */
    private int $defaultUserId;


    /**
     * @var int
     */
    private int $defaultTopicId;


    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * @var LockInterface|null
     */
    private ?LockInterface $lock;


    /**
     * Constructor.
     *
     * @param CanvasDb                              $canvasDb
     * @param IApi                                  $api
     * @param IConfigLoader                         $configLoader
     * @param \Schoolbox\Resource\Folder\Mapper     $folderMapper
     * @param UserMapper                            $userMapper
     * @param \Schoolbox\Comms\News\Mapper          $newsMapper
     * @param \Schoolbox\Comms\News\Audience\Mapper $audienceMapper
     * @param \Schoolbox\Comms\News\Source\Mapper   $sourceMapper
     * @param \Schoolbox\Comms\News\Topic\Mapper    $topicMapper
     * @param StrategyFactory                       $strategyFactory
     * @param int                                   $defaultUserId
     * @param int                                   $defaultTopicId
     * @param LoggerInterface|null                  $log
     * @param LockInterface|null                    $lock
     */
    public function __construct(
        CanvasDb $canvasDb,
        IApi $api,
        IConfigLoader $configLoader,
        \Schoolbox\Resource\Folder\Mapper $folderMapper,
        \Schoolbox\UserMapper $userMapper,
        \Schoolbox\Comms\News\Mapper $newsMapper,
        \Schoolbox\Comms\News\Audience\Mapper $audienceMapper,
        \Schoolbox\Comms\News\Source\Mapper $sourceMapper,
        \Schoolbox\Comms\News\Topic\Mapper $topicMapper,
        StrategyFactory $strategyFactory,
        int $defaultUserId,
        int $defaultTopicId,
        ?LoggerInterface $log = null,
        ?LockInterface $lock = null
    ) {
        $this->canvasDb = $canvasDb;
        $this->api = $api;
        $this->configLoader = $configLoader;
        $this->folderMapper = $folderMapper;
        $this->userMapper = $userMapper;
        $this->newsMapper = $newsMapper;
        $this->audienceMapper = $audienceMapper;
        $this->sourceMapper = $sourceMapper;
        $this->topicMapper = $topicMapper;
        $this->strategyFactory = $strategyFactory;
        $this->defaultUserId = $defaultUserId;
        $this->defaultTopicId = $defaultTopicId;
        $this->log = $log;
        $this->lock = $lock;
    }


    /**
     * Import the Announcements for each canvas Course,
     * saving them in schoolbox as news articles,
     * logging as we go
     *
     * @return void
     * @throws \Alaress\Database\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Define default author
         * Canvas Importer doesn't create user accounts; they're presumed toe exist somehow
         * but in the case where they don't exist in schoolbox.user, assume a default owner
         * We don't really have anyone, so assign Schoolbox Admin arbitrarily...
         * (There should be a non-human system user account that can be given this kind of data)
         */
        try {
            $defaultOwner = $this->userMapper->findById($this->defaultUserId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->logInfo('Default owner not found; exiting');
            return;
        }

        /* Default topic for all imported articles
         */
        try {
            $defaultTopic = $this->topicMapper->findById($this->defaultTopicId);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->logInfo('Default topic not found; exiting');
            return;
        }

        $courseCodes = $this->generateCourseCodes();

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

        $this->importAnnouncements($announcements, $courseCodes, $defaultOwner, $defaultTopic);
    }


    /**
     * Geneate a lookup of (context code, folder instance) tuples
     *
     * @return array
     * @throws \Alaress\Database\Exception
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     */
    private function generateCourseCodes(): array
    {
        /* Use the internal db to interrogate canvas_data_<instanceId>.course_dim
         * for all the Courses that we imported; contextCodes are stored in the canvas_id
         * column, but you'll need to prefix it with "course_" before you can use
         * them in the canvas api
         */

        $query = "
            SELECT
                CONCAT('course_', id) AS contextCode,
                name,
                course_code as code
            FROM courses
        ";

        if (!$this->canvasDb->isCanvasData2) {
            $query = "
            SELECT
                CONCAT('course_', canvas_id) AS contextCode,
                name,
                code
            FROM course_dim
        ";
        }

        $statement = $this->canvasDb->getConnector()->executeQuery($query);

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
                    'Course code %s does not have a corresponding schoolbox folder %s; skipping',
                    $row['code'],
                    $folderCode
                ));

                continue;
            }

            /* Add it to the stack
             */
            $courseCodes[$row['contextCode']] = $folder;
        }

        return $courseCodes;
    }

    /**
     * Get an indexed array of guids that we previously imported so we can quickly
     * skip over them, instead of repeatedly interrogating the db
     *
     * @return array
     * @throws \PDOException
     */
    private function generateLookup(): array
    {
        $query = "
            SELECT guid
            FROM {$this->sourceMapper->getTable()}
            WHERE source_name = :sourceName
            ORDER BY guid
        ";

        $statement = $this->sourceMapper->getDatabase()->prepare($query);
        $statement->execute([
            'sourceName' => self::SOURCE_NAME
        ]);

        $lookup = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $lookup[$row['guid']] = true;
        }

        return $lookup;
    }


    /**
     * Import $announcements into folders identified by $courseCodes
     * Assigns $defaultOwner as the author if the actual author can't be found in schoolbox.user
     *
     * @param array                             $announcements
     * @param array                             $courseCodes
     * @param \Schoolbox\User                   $defaultOwner
     * @param \Schoolbox\Comms\News\Topic\Model $defaultTopic
     * @return void
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\InsufficientHydrationException
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     * @throws \UnexpectedValueException
     */
    private function importAnnouncements(
        array $announcements,
        array $courseCodes,
        \Schoolbox\User $defaultOwner,
        \Schoolbox\Comms\News\Topic\Model $defaultTopic
    ): void {
        /* Build a lookup of previously-imported Announcement ids
         * This allows us to skip announcements without touching the db at all
         *
         * If you want to update published news, you can use this as a shortcut
         * to obtaining the article instance; otherwise just get an indexed list
         * of canvas announcement ids that we can ignore later
         */
        $lookup = $this->generateLookup();

        /* Every Announcement comes with a context_code field which you can use
         * to figure out the folder into which it should be placed, by using
         * $courseCodes as a hash lookup
         */
        foreach ($announcements as $announcement) {
            $owner = null;

            /* Refresh lock
             */
            if (null !== $this->lock) {
                $this->logDebug(sprintf(
                    'Refreshing lock for another %s seconds',
                    AnnouncementsTool::LOCK_TTL
                ));

                $this->lock->refresh();
            }

            /* This announcement was previously imported in a published state
             */
            if (array_key_exists($announcement['id'], $lookup)) {
                $this->logDebug(sprintf(
                    '%s announcement %s: "%s" by %s was previously imported; skipping',
                    $announcement['context_code'],
                    $announcement['id'],
                    $announcement['title'],
                    $announcement['author']['display_name'] ?? 'unknown author'
                ));

                continue;
            }

            /* Skip announcements that aren't published
             */
            if (!(int)$announcement['published']) {
                $this->logDebug(sprintf(
                    '%s announcement %s: "%s" by %s is not published in canvas; skipping',
                    $announcement['context_code'],
                    $announcement['id'],
                    $announcement['title'],
                    $announcement['author']['display_name'] ?? 'unknown author'
                ));

                continue;
            }

            $this->logInfo(sprintf(
                '%s announcement %s: found "%s"',
                $announcement['context_code'],
                $announcement['id'],
                $announcement['title'],
            ));

            /* Figure out the external id of the person who owns this
             * The external id should match someone in schoolbox.user (hopefully)
             * but if it can't, it'll fall back to $defaultOwner
             *
             * Shamefully the author id might not exist in canvas...
             */
            if (isset($announcement['author']['id']) && '' !== trim((string)$announcement['author']['id'])) {
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
                    $this->logDebug(sprintf(
                        '%s announcement %s: author %s (externalId/sis_user_id %s) not found in schoolbox users - reverting to default ownership by %s',
                        $announcement['context_code'],
                        $announcement['id'],
                        $announcement['author']['display_name'],
                        $announcementOwner['sis_user_id'],
                        $defaultOwner->getFullName()
                    ));
                }
            } else {
                $this->logDebug(sprintf(
                    '%s announcement %s: author did not have an id - reverting to default ownership by %s',
                    $announcement['context_code'],
                    $announcement['id'],
                    $defaultOwner->getFullName()
                ));
            }

            $author = $owner ?? $defaultOwner;

            /* Spawn a new article instance, then map each Announcement's fields to the article's
             */
            $article = $this->newsMapper->createModel()
                ->setTitle($announcement['title'])
                ->setBlurb('')
                ->setBody($announcement['message'])
                ->setSticky(false)
                ->setAuthor($author)
                ->setTopics($this->topicMapper->createCollection()->add($defaultTopic))
                ->setAudience(new GenericCollection(\Schoolbox\Comms\News\Audience\Model::class));

            /* Assume some details about dates to map them to schoolbox concepts:
             *
             * Submitted === announcement[posted_at]
             * Accepted === announcement[posted_at]
             * Published === announcement[delayed_post_at]
             * Expires === as set in config "news_default_expiry", if set; otherwise, postedAt
             */
            if ('' === (string)$announcement['posted_at']) {
                // likely that delayed_post_at is set
                $postedAt = \Alaress_Date_Format::newFromTimestamp(strtotime($announcement['created_at']));
            } else {
                $postedAt = \Alaress_Date_Format::newFromTimestamp(strtotime($announcement['posted_at']));
            }

            /* Set presumed expiry date using instance config
             * This is the same implementation as found elsewhere in comms/news
             *
             * If the config var didn't contain anything, just expire immediately I guess
             */
            $expiresAt = clone $postedAt;

            $expireDateOffset = (string)$this->configLoader->get(ConfigMapper::_EXPIRY_DATE_OFFSET);

            if ((new ValidDateOffset())->isSatisfiedBy($expireDateOffset)) {
                $expiresAt->modify($expireDateOffset)->endOfDay();
            }

            /* delayed_post_at can be null
             * I presume this meant "publish immediately" inside canvas
             * Use the $postedAt instead
             */
            if ('' === (string)$announcement['delayed_post_at']) {
                $publishedAt = $postedAt;

                $this->logDebug(sprintf(
                    '%s announcement %s: article did not set delayed_post_at - reusing announcement post date %s as publish date',
                    $announcement['context_code'],
                    $announcement['id'],
                    $publishedAt->getDatabase()
                ));
            } else {
                $publishedAt = \Alaress_Date_Format::newFromTimestamp(strtotime($announcement['delayed_post_at']));
            }

            // save draft
            $this->newsMapper->suppressNotification();
            $this->newsMapper->save($article);
            $draft = clone $article;

            $article
                ->setSubmittedAt($postedAt)
                ->setAcceptedAt($postedAt)
                ->setAcceptedBy($author)
                ->setPublishAt($publishedAt)
                ->setExpiresAt($expiresAt);

            /* Shank it into comms_news_article
             */
            $this->newsMapper->save($article);
            $this->newsMapper->restoreNotification();

            $this->newsMapper->propagate(
                $author,
                Mapper::METHOD_UPDATE,
                $article,
                $this->newsMapper->diff($article, $draft)
            );

            /* Update the article source table so we know this article's origins
             * That allows us to skip over it faster next time
             *
             * Since it's saved in a Published state, we don't need to ever update it, really
             */
            $source = $this->sourceMapper->createModel()
                ->setArticle($article)
                ->setSourceName(self::SOURCE_NAME)
                ->setGuid((string)$announcement['id']);

            $this->sourceMapper->save($source);

            /* Andy things. Not entirely sure if this was the intended manner in which
             * to persist audience segments, but whatever - it's a mess in there
             */
            $folder = $courseCodes[$announcement['context_code']];

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
                '%s announcement %s: article has audiencing segment (%s, %s, %s)',
                $announcement['context_code'],
                $announcement['id'],
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
