<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Helper;

/**
 * Devs Only
 *
 * CLI Tool for deleting Group Attachments out of arbitrary Turnitin Groups
 * that we may have spawned during vagrant testing - reprovisioning will put
 * us in a situation where the same identifier becomes reused, but it won't
 * necessarily be what you expected since others can pollute the same namespace
 */
class CleanserTool extends CliTool\App
{
    /**
     * Lock name
     */
    public const TURNITIN_LOCK_NAME = 'turnitin_group_attachment_cleanser';


    /**
     * Only purge -vagrant suffixed Turnitin Groups
     */
    private const SITE_TYPE = 'vagrant';


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
     * --start=...
     */
    private const OPT_LONG_START = 'start:';


    /**
     * --end=...
     */
    private const OPT_LONG_END = 'end:';


    /**
     * Lock ttl (seconds)
     *
     * Reserves this amount for every iteration,
     * refreshing at the start of every iteration
     */
    private const LOCK_TTL = 60;


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_INSTANCE,
            self::OPT_LONG_START,
            self::OPT_LONG_END
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Devs Only

There's no programmatic mechanism to delete Turnitin Groups so once you've built them, they are
forever there. That means if you're reprovisioning and adding files and reprovisioning and
adding files, you'll end up polluting the same Turnitin Group with all your tests.

This tool will arbitrarily look through a sequence of Turnitin Groups as per our convention and
just...delete Turnitin Group Attachments from them, so you can test freshly.

This is a dev tool and should not be used in the normal scheme of things

Usage: cleanser.php --start=300 --end=400 [--instance=INSTANCE_ID]


HELP;
            exit;
        }

        /* Check if turnitin is configured sufficiently
         */
        if (!(new Helper($this->getConfig()))->isConfigured()) {
            $this->getLogger()->info('Turnitin configuration invalid.');
            exit;
        }

        /* Check that your api setup is functioning
         * We need this api working
         */
        $api = new Turnitin\Api(
            $this->getConfig()->get('site_version'),
            $this->getConfig()->get('turnitin_api_host'),
            $this->getConfig()->get('turnitin_api_key')
        );

        /* If the basic test() failed, then your turnitin config is broken
         * and your api host/key combo is somehow wrong
         */
        try {
            $api->test();
        } catch (\Exception $e) {
            $this->getLogger()->warning($e->getMessage());
            exit(1);
        }

        /* Check that this isn't currently running
         */
        try {
            $lock = $this->acquireLock(self::TURNITIN_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            exit(1);
        }

        /* Needed both the Start and End options
         */
        if (!$this->getOpt(self::OPT_LONG_START) || !$this->getOpt(self::OPT_LONG_END)) {
            $this->getLogger()->info('You need to supply both the start and end submission box ids');
            return;
        }

        /* Find all the submission boxes between --start and --end primary keys
         * These are ones we probably added during testing in our vagrants
         */
        $boxIds = range((int)$this->getOpt(self::OPT_LONG_START), (int)$this->getOpt(self::OPT_LONG_END));

        $codec = new Turnitin\Group\IdCodec();
        $groupIds = [];

        foreach ($boxIds as $boxId) {
            $groupIds[] = $codec->encode($boxId, self::SITE_TYPE);
        }

        /* Now find out what Turnitin knows
         */
        foreach ($groupIds as $groupId) {
            $this->getLogger()->debug(sprintf(
                'Refreshing lock for another %s seconds',
                self::LOCK_TTL
            ));

            $lock->refresh();

            /* Get the group
             * If it doesn't exist, don't worry about it and continue
             */
            try {
                $group = $api->retrieve('GET', "/api/v1/groups/{$groupId}", []);
            } catch (Turnitin\Exception\BadEndpoint $e) {
                /* Group doesn't exist; noop
                 */
                $this->getLogger()->debug(sprintf(
                    'Turnitin Group %s: not found - skipping',
                    $groupId
                ));

                continue;
            }

            if ($group) {
                /* If you got the group, go find some Turnitin Group Attachments
                 */
                $attachments = $api->retrieve('GET', "/api/v1/groups/{$groupId}/attachments", []);

                /* If there are any, purge them manually
                 */
                foreach ($attachments['attachments'] as $attachment) {
                    $this->getLogger()->debug(sprintf(
                        'Turnitin Group %s: found Group Attachment %s',
                        $groupId,
                        $attachment['id']
                    ));

                    $this->getLogger()->debug(sprintf(
                        'Refreshing lock for another %s seconds',
                        self::LOCK_TTL
                    ));

                    $lock->refresh();

                    try {
                        $api->retrieve('DELETE', "/api/v1/groups/{$groupId}/attachments/{$attachment['id']}", []);
                    } catch (\DomainException $e) {
                        /* No Content means you succeeded, I guess
                         */
                        if (204 === $e->getCode()) {
                            $this->getLogger()->debug(sprintf(
                                'Turnitin Group %s: deleted Group Attachment %s successfully.',
                                $groupId,
                                $attachment['id']
                            ));
                        }
                    }
                }
            }
        }

        $this->getLogger()->info('Done.');

        /* Releasing control
         */
        $lock->release();
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
