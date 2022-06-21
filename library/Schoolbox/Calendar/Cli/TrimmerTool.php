<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Cli;

use Schoolbox\Application\CliTool;
use Symfony\Component\Lock\LockInterface;

require_once __DIR__ . '/../../../../conf/core/require.php';

/**
 * This finds all the dead schoolbox events and removes them.
 * These are targeted for termination:
 *
 *     ultra-old events
 *     soft-deleted events
 *     deprecated tasscalendar events
 */
class TrimmerTool extends CliTool\App
{
    /**
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'event_trimmer';


    /**
     * Decide how long this should be based on your usage
     * You might have to adjust it, but it should cover the cost of an iteration
     */
    private const LOCK_TTL = 60;


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * Number of event ids to process, per chunk
     * TODO: Potentially configurable on as a flag
     */
    private const CHUNK_SIZE = 500;


    /**
     * A list of all schema where an event might exist
     * These are mutated in-order
     */
    private const TABLES = [
        'event_attendance' => 'event_id',
        'event_campus' => 'event_id',
        'event_folder' => 'event_id',
        'event_role' => 'event_id',
        'event_source' => 'event_id',
        'event_user' => 'event_id',
        /* This must be last and can only be deleted once we've removed
         * all the foreign key dependencies
         */
        'event' => 'id'
    ];


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Dead Event Trimmer finds extraneous events and removes them, along with their relationships.

Dead Event candidates are those which:
    * are soft deleted
    * finished more than 1 year ago
    * used old suds tassapi source names ParentCalendar, ParentNotices, StudentCalendar, etc. et al.
    
There is no planning mode, but you must use --perform to mutate data.

Usage: php trimmer.php [--perform]


HELP;
            exit;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            exit;
        }

        /* Set 1: Find all the soft-deleted events from everywhere
         */
        $softDeletedEventIds = $this->findSoftDeletedEvents();

        /* Display count of candidate events found
         * This lets you manually correlate what you might see in workbench explorations
         */
        $this->getLogger()->info(sprintf(
            'Permanently hard-deleting %s soft-deleted event(s)',
            \count($softDeletedEventIds)
        ));

        $this->permanentlyDelete($lock, $softDeletedEventIds);


        /* Set 2: Find old tasscalendar softdeleted remnants
         * These exist because eventmapper softdeleted events
         * These were deprecated in favour of using suds_tassapi source name.
         *
         * A fix should have already prevented these from being active
         * (they should already be soft deleted) but check anyway
         *
         * Find all the events that used the ancient tasscalendar source names
         * Any event using these old source names should be permanently deleted:
         *     ParentCalendar, ParentNotices, StudentCalendar, StudentNotices, TeacherCalendar, TeacherNotices
         */
        $oldTassEventIds = $this->findOldTassEvents();

        /* Display count of candidate events found
         * This lets you manually correlate what you might see in workbench explorations
         */
        $this->getLogger()->info(sprintf(
            'Permanently hard-deleting %s old suds tass event(s)',
            \count($oldTassEventIds)
        ));

        $this->permanentlyDelete($lock, $oldTassEventIds);


        /* Set 3: old events that finished more than a year ago
         */
        $ancientEventIds = $this->findAncientEvents();

        /* Display count of candidate events found
         * This lets you manually correlate what you might see in workbench explorations
         */
        $this->getLogger()->info(sprintf(
            'Permanently hard-deleting %s ancient event(s) that ended ages ago',
            \count($ancientEventIds)
        ));

        $this->permanentlyDelete($lock, $ancientEventIds);


        /* Add more data sets here, as needed
         */


        /* We're outta here
         */
        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }


    /**
     * Set 1: Find all the soft-deleted events from everywhere
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function findSoftDeletedEvents(): array
    {
        $statement = $this->getDatabaseInternal()->query("
           SELECT id
           FROM `event`
           WHERE deleted_at IS NOT NULL            
        ");

        $statement->execute();

        return $statement->fetchColumnArray('id');
    }


    /**
     * Set 2: Find all the old tass events which used the old source_names
     *
     * These should already have been soft-deleted, resulting in no identifiers
     * found; but, in the weird instance where they did exist, they should be removed
     * This guarantees their termination in case e.g. an updated failed, leaving broken data
     *
     * TODO You can remove this set once they no longer exist in the wild
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function findOldTassEvents(): array
    {
        $statement = $this->getDatabaseInternal()->query("
           SELECT e.id
           FROM `event` e
           INNER JOIN event_source es ON e.id = es.event_id
           WHERE e.deleted_at IS NOT NULL
           AND es.source_name IN ('ParentCalendar', 'ParentNotices', 'StudentCalendar', 'StudentNotices', 'TeacherCalendar', 'TeacherNotices')            
        ");

        $statement->execute();

        return $statement->fetchColumnArray('id');
    }


    /**
     * Set 3: Find events that finished ages ago
     * TODO: Interval (ancientness) could be made configurable
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    private function findAncientEvents(): array
    {
        $statement = $this->getDatabaseInternal()->query("
           SELECT id
           FROM `event`
           WHERE end_datetime < NOW() - INTERVAL 1 YEAR       
        ");

        $statement->execute();

        return $statement->fetchColumnArray('id');
    }


    /**
     * Permanently delete all the set of identifiers, $eventIds,
     * and all their relationships
     *
     * @param LockInterface $lock
     * @param array         $eventIds
     * @return void
     * @throws \Alaress\Database\Exception
     * @throws \Symfony\Component\Lock\Exception\LockAcquiringException
     * @throws \Symfony\Component\Lock\Exception\LockConflictedException
     */
    private function permanentlyDelete(LockInterface $lock, array $eventIds): void
    {
        $chunks = array_chunk($eventIds, self::CHUNK_SIZE);

        /* For each n-sized chunk,
         * Refresh lock, giving yourself TTL seconds to process this next set of operations
         */
        foreach ($chunks as $chunk) {
            $lock->refresh();

            /* Prep them for a query
             */
            $ids = implode(',', $chunk);

            /* Only execute mutations if you're in perform mode
             */
            if ($this->perform()) {
                /* For each event in the chunk,
                 *     Delete its relationships in the relationship tables:
                 *     (can probably fit this into a series of chunked deletion join WHERE event.id IN ...)
                 *     event_attendance, event_campus, event_folder, event_role, event_source, event_user
                 *
                 *     Then delete the event itself
                 */
                foreach (self::TABLES as $table => $identifier) {
                    $this->getLogger()->debug(sprintf(
                        'Deleting %s candidate events from %s table',
                        \count($chunk),
                        $table
                    ));

                    $statement = $this->getDatabaseInternal()->query("
                        DELETE FROM {$table}
                        WHERE {$identifier} IN ({$ids})
                    ");

                    $statement->execute();
                }
            }
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
