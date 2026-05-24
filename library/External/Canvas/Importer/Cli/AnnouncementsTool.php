<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer\Cli;

use External\Canvas\Log\Mapper as CanvasLogMapper;
use Schoolbox\Application\CliTool;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Ramsey\Uuid\Uuid;

/**
 * CLI Tool for processing Canvas Announcements
 */
class AnnouncementsTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const CANVAS_ANNOUNCEMENTS_LOCK_NAME = 'canvas_announcements';

    /**
     * This script name appears in the import log
     */
    public const CANVAS_ANNOUNCEMENTS_SCRIPT_NAME = 'Canvas Announcements';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --instance=...
     */
    private const OPT_LONG_INSTANCE = 'instance:';


    /**
     * --owner=
     */
    private const OPT_LONG_DEFAULT_OWNER = 'owner:';


    /**
     * --topic=
     */
    private const OPT_LONG_DEFAULT_TOPIC = 'topic:';


    /**
     * Lock ttl (seconds)
     *
     * Reserves this amount for every iteration,
     * refreshing at the start of every iteration
     */
    public const LOCK_TTL = 60;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_DEFAULT_OWNER,
            self::OPT_LONG_DEFAULT_TOPIC,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import canvas' course announcements into schoolbox folder news articles.

owner/topic will use values from admin, or can be overloaded here

Overload: you need to specify a schoolbox user id as the default owner of these
articles if we can't find their actual owner.

Overload: you need to specify a schoolbox topic id as the default topic of these
articles because that concept doesn't exist in Canvas and articles get lost in
the noise of everything else in schoolbox without a topic to segregate them

Usage: announcements.php --owner=<user id> --topic=<topic id> [--perform]


HELP;
            exit;
        }

        // only run if canvas integration is enabled
        if (!$this->getConfig()->get(ConfigMapper::NAME_CANVAS_ENABLED)) {
            $this->getLogger()->info('Not running on a canvas plan. Exiting.');
            exit();
        }
        // again check if we have canvas news import enabled
        if (!$this->getConfig()->get(ConfigMapper::NAME_CANVAS_SYNC_NEWS)) {
            $this->getLogger()->info('Not syncing canvas news. Exiting.');
            exit();
        }

        /* Check that this isn't currently running
         */

        try {
            $lock = $this->acquireLock(self::CANVAS_ANNOUNCEMENTS_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            $lock = null;
        }

        if ($lock) {
            $bearerToken = $this->getConfig()->get('canvas_bearer_token');

            if ('' === trim((string)$bearerToken)) {
                $this->getLogger()->info('Provide an authentication token to continue.');
                exit();
            }

            /* Need to have a default owner param
             */
            $defaultOwner = $this->getOpt(self::OPT_LONG_DEFAULT_OWNER);
            if ('' === trim((string)$defaultOwner)) {
                // check if we have something set in admin...
                $defaultOwner = $this->getConfig()->get(ConfigMapper::NAME_CANVAS_NEWS_AUTHOR);
                if ('' === trim((string)$defaultOwner)) {
                    $this->getLogger()->info('You need to specify a default article owner (a schoolbox user id)');
                    return;
                }
            }

            /* Need to have a default topic param
             */
            $defaultTopic = $this->getOpt(self::OPT_LONG_DEFAULT_TOPIC);
            if ('' === trim((string)$defaultTopic)) {
                // check if we have something set in admin...
                $defaultTopic = $this->getConfig()->get(ConfigMapper::NAME_CANVAS_NEWS_TOPIC);
                if ('' === trim((string)$defaultTopic)) {
                    $this->getLogger()->info('You need to specify a default topic (a schoolbox topic id)');
                    return;
                }
            }

            // log this to keep track of things
            $runId = (string)Uuid::uuid4();
            $importLogMapper = CanvasLogMapper::getInstance();

            /* Trim away ancient logs
             */
            $importLogMapper->prune(self::CANVAS_ANNOUNCEMENTS_SCRIPT_NAME);

            $importLog = $importLogMapper->start($runId, self::CANVAS_ANNOUNCEMENTS_SCRIPT_NAME);

            /* Used to shank a database instance
             */
            $db = \Schoolbox\UserMapper::getInstance()->getDatabase();

            /* Wrap in transaction so changes are atomic
             */
            $db->beginTransaction();

            /** @var StrategyFactory $strategyFactory */
            $strategyFactory = $this->getDi()->get('commsNewsAudienceStrategyFactory');

            try {
                (new \External\Canvas\Importer\Announcements(
                    new \External\Canvas\Importer\CanvasDb($this->getConfig()),
                    new \External\Canvas\Importer\Api(
                        $this->getConfig()->get('canvas_url'),
                        $bearerToken
                    ),
                    $this->getConfig(),
                    \Schoolbox\Resource\Folder\Mapper::getInstance(),
                    \Schoolbox\UserMapper::getInstance(),
                    \Schoolbox\Comms\News\Mapper::getInstance(),
                    \Schoolbox\Comms\News\Audience\Mapper::getInstance(),
                    \Schoolbox\Comms\News\Source\Mapper::getInstance(),
                    \Schoolbox\Comms\News\Topic\Mapper::getInstance(),
                    $strategyFactory,
                    (int)$defaultOwner,
                    (int)$defaultTopic,
                    $this->getLogger(),
                    $lock
                ))->import();
            } catch (\Throwable $e) {
                // stop the transaction
                $db->rollBack();
                // log the error
                $importLogMapper->error($importLog, $e->getMessage());

                // re-throw
                throw $e;
            }

            /* Only commit changes in Perform mode
             */
            if ($this->perform()) {
                $db->commit();
            } else {
                $db->rollBack();

                $this->getLogger()->info('Planning finished.');
            }

            /* Releasing control
             */
            $lock->release();

            // log completion
            $importLogMapper->finish($importLog);
        } else {
            $this->getLogger()->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit;
        }
    }


    /**
     * FIXME Decide whether this script must be run as root
     *  By default, this returns false (see CliTool\App) but if you're using methods that require
     *  access to special folders (such as when you're creating files) you need to run this as root.
     *  Otherwise it'll fail with a generic error that might mislead you
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return parent::runAsRoot();
    }
}
