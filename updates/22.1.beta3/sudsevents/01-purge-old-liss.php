<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 *
 * Copy this to /tools folder
 *
 * To redirect log output to a file(s):
 *     STDOUT is file descriptor 1
 *     STDERR is file descriptor 2; our logger output ends up going here
 *
 *     Example: php example.php 2> log.txt
 */
$app = new class extends CliTool\App
{
    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * An arbitrarily-chosen string to denote where these events came from
     *
     * For the liss implementation, it was actually a concat between liss_ and
     * some value the entered in the db (the schoolCode)
     *
     * We wanted to blow away all the events that used our liss_ prefix regardless
     * of what this configured schoolCode value might have been over the years
     */
    private const EVENT_IMPORT_IDENTIFIER = 'liss_';


    /**
     * Events scheduled for delation are marked by having their updated_at set
     * to this date. Other suds importers use this same philosophy so to help
     * deconflict them all, I use this purge date uniquely here. Other suds
     * purgers should choose their own unique datetime to avoid conflict
     */
    private const PURGE_DATE = '1901-01-01 01:11:11';

    /**
     * @var \Event\Source\Mapper
     */
    private \Event\Source\Mapper $eventSourceMapper;


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

Once-off correctional tool

This ALWAYS EXECUTES a hard-delete of liss calendar events

This removes the old liss Events implementation which put timetable events into
the schoolbox.event table, and all its relational pieces. This has since moved to
SUDS Timetable and is no longer required here where it pollutes the event space.

This always runs in perform mode as part of the v22 update

Usage: php 01-purge-old-liss.php


HELP;
            exit;
        }

        /* Check if you're running LISS connectivity first
         * We can arbitrarily decide this by the presence of (username, password, schoolCode) tuple
         *
         * If you are, continue deleting the event records associated with a suds_compass import
         * (these should be loaded into SUDS Timetable instead of schoolbox.events, separately)
         *
         * Otherwise, don't do anything and just exit politely
         * This script had the potential to cross-over into tass calendar which also uses the same
         * concept of setting potentially-deleteable events' updated_at to 0000-00-00
         */
        $username = '' !== trim((string)$this->getConfig()->get('external_liss_username'));
        $password = '' !== trim((string)$this->getConfig()->get('external_liss_password'));
        $schoolCode = '' !== trim((string)$this->getConfig()->get('external_liss_schoolcode'));

        if (!($username && $password && $schoolCode)) {
            $this->getLogger()->info('LISS purge did not run: this schoolbox instance did not have a valid a LISS configuration. No changes required; exiting');
            exit;
        }

        $this->eventSourceMapper = \Event\Source\Mapper::getInstance();

        $queries = [];

        $purgeDate = self::PURGE_DATE;
        $importIdentifier = self::EVENT_IMPORT_IDENTIFIER . '%';

        /* Mark events for deletion to avoid a mega where-in clause
         */
        $queries['Event Primer'] = [
            'query' => "
                UPDATE `event` e
                INNER JOIN event_source s on e.id = s.event_id
                SET e.updated_at = '{$purgeDate}'
                WHERE s.source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        /* Avoid using the eventSourceMapper to delete things via objects etc.
         * It is super super slow for heaps of events
         *
         * Just assassinate them directly
         */
        $queries['Event Attendance Relations'] = [
            'query' => "
                DELETE a
                FROM `event` e
                INNER JOIN event_attendance a ON a.event_id = e.id
                INNER JOIN event_source s ON e.id = s.event_id
                WHERE s.source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        $queries['Event Campus Relations'] = [
            'query' => "
                DELETE c
                FROM `event` e
                INNER JOIN event_campus c ON c.event_id = e.id
                INNER JOIN event_source s ON e.id = s.event_id
                WHERE s.source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        $queries['Event Folder Relations'] = [
            'query' => "
                DELETE f
                FROM `event` e
                INNER JOIN event_folder f ON f.event_id = e.id
                INNER JOIN event_source s ON e.id = s.event_id
                WHERE s.source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        $queries['Event Role Relations'] = [
            'query' => "
                DELETE r
                FROM `event` e
                INNER JOIN event_role r ON r.event_id = e.id
                INNER JOIN event_source s ON e.id = s.event_id
                WHERE s.source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        $queries['Event User Relations'] = [
            'query' => "
                DELETE u
                FROM `event` e
                INNER JOIN event_user u ON u.event_id = e.id
                INNER JOIN event_source s ON e.id = s.event_id
                WHERE s.source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        $queries['Event Source'] = [
            'query' => "
                DELETE FROM event_source
                WHERE source_name LIKE '{$importIdentifier}'
            ",
            'params' => null
        ];

        $queries['Event Detonator'] = [
            'query' => "
                DELETE FROM event
                WHERE updated_at = '{$purgeDate}'
            ",
            'params' => null
        ];

        $this->getLogger()->debug(sprintf(
            'Beginning the great purge of existing events/relations that used event_source prefix: %s',
            self::EVENT_IMPORT_IDENTIFIER
        ));

        $this->getLogger()->debug('This might take a while.');
        $this->getLogger()->debug('');

        /* Delete relations
         */
        foreach ($queries as $name => $setup) {
            $this->getLogger()->debug(sprintf(
                'Executing %s purge...',
                $name
            ));

            $statement = $this->eventSourceMapper->getDatabase()->prepare($setup['query']);

            try {
                if (null !== $setup['params']) {
                    $statement->execute($setup['params']);
                } else {
                    $statement->execute();
                }
            } catch (\Exception $e) {
                /* Something went wrong with this query somehow
                 */
                print_r($setup['query']);
            }

            $this->getLogger()->debug('Done.');
        }

        $this->getLogger()->info(sprintf(
            'Purged existing events/relations that used event_source prefix: %s',
            self::EVENT_IMPORT_IDENTIFIER
        ));
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
