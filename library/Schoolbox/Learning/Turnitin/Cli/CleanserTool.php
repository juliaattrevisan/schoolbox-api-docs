<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Cli;

use Alaress\Lock\InstanceLockFactory;
use Predis\ClientInterface;
use Schoolbox\Application\CliTool;
use Schoolbox\Learning\Turnitin;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * Devs Only
 *
 * CLI Tool for deleting Group Attachments out of arbitrary Turnitin Groups
 * that we may have spawned during vagrant testing - reprovisioning will put
 * is in a situation where the same identifier becomes reused, but it won't
 * necessarily be what you expected since others can pollute the same namespace
 */
class CleanserTool extends CliTool\App
{

    /**
     * Lock name
     */
    public const TURNITIN_LOCK_NAME = 'turnitin_group_attachment_cleanser';


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
     * Lock ttl
     */
    private const LOCK_TTL = 10 * 60;


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

Devs Only

There's no programmatic mechanism to delete Turnitin Groups so once you've built them, they are
forever there. That means if you're reprovisioning and adding files and reprovisioning and
adding files, you'll end up polluting the same Turnitin Group with all your tests.

This tool will arbitrarily look through a sequence of Turnitin Groups as per our convention and
just...delete Turnitin Group Attachments from them, so you can test freshly.

This is a dev tool and should not be used in the normal scheme of things

Usage: group.php [--instance=INSTANCE_ID] 


HELP;
            exit;
        }

        /* Check that this isn't currently running
         */

        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;

        $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
        $lock        = $lockFactory->createLock(self::TURNITIN_LOCK_NAME, self::LOCK_TTL);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }

        if ($acquired) {
            /* We need this api
             */
            $api = new Turnitin\Api(
                $this->getConfig()->get('site_version'),
                $this->getConfig()->get('turnitin_api_host'),
                $this->getConfig()->get('turnitin_api_key')
            );

            /* Find all the submission boxes between 700 and 800 primary id
             * These are ones we probably added during testing in our vagrants
             */
            foreach (range(305, 400) as $id) {
                $groupIds[] = (new Turnitin\Group\IdCodec())->encode($id, 'vagrant');
            }

            /* Now find out what Turnitin knows
             */
            foreach ($groupIds as $groupId) {
                /* Get the group
                 * If it doesn't exist, don't worry about it and continue
                 */
                try {
                    $group = $api->retrieve('GET', "/api/v1/groups/{$groupId}", []);
                } catch (Turnitin\Exception\BadEndpoint $e) {
                    /* Group doesn't exist; noop
                     */
                    $this->getLogger()->debug(sprintf(
                        'Turnitin Group %s not found; skipping',
                        $groupId
                    ));

                    continue;
                }

                if ($group) {
                    /* If you got the group, go find some Turnitin Group Attachments
                     */
                    $attachments = $api->retrieve('GET', "/api/v1/groups/{$groupId}/attachments", []);

                    foreach ($attachments as $attachment) {
                        /* If there are any, purge them manually
                         */
                        print_r($attachment);
                    }
                }
            }

            $this->getLogger()->info('Done.');

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