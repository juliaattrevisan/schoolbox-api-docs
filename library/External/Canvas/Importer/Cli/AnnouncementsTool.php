<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer\Cli;

use Alaress\Lock\InstanceLockFactory;
use Predis\ClientInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

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
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Import canvas' course announcements into schoolbox folder news articles

Usage: announcements.php [--perform]


HELP;
            exit;
        }

        /* Check that this isn't currently running
         */

        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;

        $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
        $lock        = $lockFactory->createLock(self::CANVAS_ANNOUNCEMENTS_LOCK_NAME, self::LOCK_TTL);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }

        if ($acquired) {
            $bearerToken = $this->getConfig()->get('canvas_bearer_token');

            if ('' === trim((string)$bearerToken)) {
                $this->getLogger()->info('Provide an authentication token to continue.');
                exit();
            }

            /** @var StrategyFactory $strategyFactory */
            $strategyFactory = $this->getDi()->get('commsNewsAudienceStrategyFactory');

            (new \External\Canvas\Importer\Announcements(
                new \External\Canvas\Importer\CanvasDb(),
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
                $strategyFactory,
                $this->getLogger(),
                $lock
            ))->import();

            if (!$this->perform()) {
                $this->getLogger()->info('Planning finished.');
            }

            /* Releasing control
             */
            $lock->release();
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
