<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

namespace SChoolbox\Calendar\Cli;

use Schoolbox\Application\CliTool;

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
    public const MY_TOOL_LOCK_NAME = 'example';


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
     * --flag
     *
     * An example arg that's a boolean on/off
     */
    private const OPT_LONG_FLAG = 'flag';


    /**
     * --input
     *
     * An example arg that has an actual value, denoted by the colon
     */
    private const OPT_LONG_INPUT = 'input:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FLAG,
            self::OPT_LONG_INPUT
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Write an explanation about what your tool does and what arguments it accepts

Usage: example.php [--flag] [--input mytest.txt]
        --flag      Example flag (on/off)
        --input     Example input value (arg which has user-inputted value)


HELP;
            exit;
        }

        /* Decide how loudly to announce lock acquisition failure
         *
         * Simply using $lock = $this->acquireLock() will keep you safe, even if you don't
         * specifically check for acquisition
         */
        try {
            $lock = $this->acquireLock(self::MY_TOOL_LOCK_NAME, self::LOCK_TTL);
        } catch (\RuntimeException $e) {
            exit();
        }

        /* Set 1: Find all the soft-deleted events from everywhere
         */

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

        /* Set 3: old events that finished more than a year ago
         */

        /* For each set of identifiers,
         *     (The list of identifiers could contain literally zillions of event ids
         *     where each event has FKs everywhere, so we'll have to do them in groups
         *     Do 500 events at a time, I guess, by chunking this list)
         *
         *     Display count of candidate events found
         *     This lets you manually correlate what you might see in workbench explorations
         *
         *     For each n-sized chunk,
         *         Refresh lock, giving yourself TTL seconds to process this next set of operations
         *
         *         For each event in the chunk,
         *             if perform mode is enabled,
         *                 Delete its relationships in the relationship tables:
         *                 (can probably fit this into a series of chunked deletion join WHERE event.id IN ...)
         *                 event_attendance, event_campus, event_folder, event_role, event_source, event_user
         *
         *                 Then delete the event itself
         *
         *             Otherwise, in planning mode,
         *                 log the name of the event and its id as being a candidate for removal
         */

//        /* TODO Implement your iterations here, refreshing lock each time
//         *  This will grant you LOCK_TTL more seconds to do another iteration,
//         *  until the script unlocks itself
//         */
//        try {
//            for ($i = 0; $i < 10; $i++) {
//                $this->refreshLock($lock);
//
//                /* Your logic goes here
//                 */
//            }
//        } catch (\Alaress\Lock\LockRefreshException $e) {
//            /* Lock TTL expired; just leave politely
//             */
//            exit();
//        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
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