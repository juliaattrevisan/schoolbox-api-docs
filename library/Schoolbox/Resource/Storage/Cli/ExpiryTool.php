<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Cli;

use Schoolbox\Application\CliTool;

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

    /** @inheritDoc */
    public function getRequiredProcessUser(): ?string
    {
        return 'www-data';
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
        try {
            $lock = $this->acquireLock(self::EXPIRED_STORAGE_REMOVAL_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            $this->getLogger()->warning("Cannot acquire lock. Likely previous execution still in progress. Exiting");
            exit(1);
        }

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
        $relationalDelete = new \Schoolbox\Resource\Storage\RelationalDelete\RelationalDelete($this->getLogger());
        if ($this->perform()) {
            /* In Perform mode, blow them away
             */

            foreach ($items as $item) {
                $lock->refresh();

                $relationalDelete->remove($item->getHash(), false);
            }
        } else {
            /* In Planning mode, show them in the cli
             */
            foreach ($items as $item) {
                $lock->refresh();
                $relationalDelete->remove($item->getHash(), true);

                $this->getLogger()->debug(sprintf(
                    '%s: %s -  expired on %s',
                    $item->getHash(),
                    $item->getBasename(),
                    $item->getExpiryAt()->getDatabase()
                ));
            }

            $this->getLogger()->debug('Planning finished.');
        }
    }
}
