<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Cli;

use Alaress\Lock\InstanceLockFactory;
use Predis\ClientInterface;
use Schoolbox\Application\CliTool;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\RedisStore;

/**
 * Removes storage files that fulfil all these conditions:
 *     - expired_at is not null
 *     - expired_at is in the past
 */
class ExpiryTool extends CliTool\App
{

    /**
     * Lock name for the Inspector
     */
    public const EXPIRED_STORAGE_REMOVAL_LOCK_NAME = 'expired_storage_removal';


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
    private const LOCK_TTL = 60;


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

Expired Storage Objects Removal Tool

This tool removes storage objects that have expired. An expired object
has a non-null storage.expired_at value that's in the past.

Planning mode gives you a list of expired files.
Perform mode blows them away, hard deleting their records from the db and file system.

Usage: expired-storage-removal.php [--perform]


HELP;
            exit;
        }

        /* Check that this isn't currently running
         */

        /** @var ClientInterface $redis */
        $redis      = $this->getDi()->get('redis.cache');
        $instanceId = $this->getOpt(self::OPT_LONG_INSTANCE) ?? INSTANCE_ID;

        $lockFactory = new InstanceLockFactory(new LockFactory(new RedisStore($redis)), $instanceId);
        $lock        = $lockFactory->createLock(self::EXPIRED_STORAGE_REMOVAL_LOCK_NAME, self::LOCK_TTL);

        try {
            $acquired = $lock->acquire();
        } catch (LockConflictedException | LockAcquiringException $e) {
            $acquired = false;
        }

        if ($acquired) {
            /* Generate a list of expired storage objects
             */
            $storageMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

            $pb = $storageMapper->getPredicateBuilder()
                ->notNull('expired_at')
                ->lessThan('expired_at', \Alaress_Date_Format::newFromTimestamp($_SERVER['REQUEST_TIME'])->getDatabase());

            $items = $storageMapper->findByPredicates($pb, [
                'order' => [
                    'expired_at' => 'desc'
                ]
            ]);

            /* No items? No trabajo
             */
            if (!$items->count()) {
                $this->getLogger()->debug('No expired items found; exiting.');
                exit;
            }

            /* Otherwise, start doing the stuff
             */
            if ($this->perform()) {
                /* In Perform mode, blow them away
                 */
                $relationalDelete = new \Schoolbox\Resource\Storage\RelationalDelete\RelationalDelete($this->getLogger());

                foreach ($items as $item) {
                    $relationalDelete->remove($item->getHash(), false);

                    $lock->refresh();
                }
            } else {
                /* In Planning mode, show them in the cli
                 */
                foreach ($items as $item) {
                    $this->getLogger()->debug(sprintf(
                        '%s: %s -  expired on %s',
                        $item->getHash(),
                        $item->getBasename(),
                        $item->getExpiryAt()->getDatabase()
                    ));

                    $lock->refresh();
                }

                $this->getLogger()->debug('Planning finished.');
            }
        }
    }
}
