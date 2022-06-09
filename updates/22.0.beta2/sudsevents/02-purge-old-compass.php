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
     */
    private const EVENT_IMPORT_IDENTIFIER = 'suds_compass';


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

This ALWAYS EXECUTES a hard-delete of compass calendar events

This removes the old Compass Events implementation which put timetable events into
the schoolbox.event table, and all its relational pieces. This has since moved to
SUDS Timetable and is no longer required here where it pollutes the event space.

This always runs in perform mode as part of the v22 update and is a reimplementation
of Compass Prepare.

Usage: php 00-purge-old-compass.php --perform


HELP;
            exit;
        }

        /* Check if you're running SUDS Compass first
         *
         * If you are, continue deleting the event records associated with a suds_compass import
         * (these should be loaded into SUDS Timetable instead of schoolbox.events, separately)
         *
         * Otherwise, don't do anything and just exit politely
         * This script had the potential to cross-over into tass calendar which also uses the same
         * concept of setting potentially-deleteable events' updated_at to 0000-00-00
         */
        if (\Schoolbox\External\Importer\Endpoint\Factory::COMPASS !== $this->getConfig()->get('schooldata_import_data_source')) {
            $this->getLogger()->info('Compass purge did not run: this schoolbox instance did not use SUDS Compass. No changes required; exiting');
            exit;
        }

        $this->eventSourceMapper = \Event\Source\Mapper::getInstance();

        $queries = [];

        /* Mark events for deletion to avoid a mega where-in clause
         */
        $queries['Event Primer'] = [
            'query' => "
                    UPDATE `event` e
                    INNER JOIN event_source s on e.id = s.event_id
                    SET e.updated_at = '0000-00-00 00:00:00'
                    WHERE s.source_name = :sourceName
                ",
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        /* Avoid using the eventSourceMapper to delete things via objects etc.
         * It is super super slow for heaps of events, such as a huge compass import
         *
         * Just assassinate them directly
         */
        $queries['Event Attendance Relations'] = [
            'query' => '
                    DELETE a
                    FROM `event` e
                    INNER JOIN event_attendance a ON a.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        $queries['Event Campus Relations'] = [
            'query' => '
                    DELETE c
                    FROM `event` e
                    INNER JOIN event_campus c ON c.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        $queries['Event Folder Relations'] = [
            'query' => '
                    DELETE f
                    FROM `event` e
                    INNER JOIN event_folder f ON f.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        $queries['Event Role Relations'] = [
            'query' => '
                    DELETE r
                    FROM `event` e
                    INNER JOIN event_role r ON r.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        $queries['Event User Relations'] = [
            'query' => '
                    DELETE u
                    FROM `event` e
                    INNER JOIN event_user u ON u.event_id = e.id
                    INNER JOIN event_source s ON e.id = s.event_id
                    WHERE s.source_name = :sourceName                 
                ',
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        $queries['Event Source'] = [
            'query' => '
                    DELETE FROM event_source
                    WHERE source_name = :sourceName
                ',
            'params' => [
                'sourceName' => self::EVENT_IMPORT_IDENTIFIER
            ]
        ];

        $queries['Event Detonator'] = [
            'query' => "
                    DELETE FROM event
                    WHERE updated_at = '0000-00-00 00:00:00'
                ",
            'params' => null
        ];

        $this->getLogger()->debug(sprintf(
            'Beginning the great purge of existing events/relations that used event_source: %s',
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

            if (null !== $setup['params']) {
                $statement->execute($setup['params']);
            } else {
                $statement->execute();
            }

            $this->getLogger()->debug('Done.');
        }

        $this->getLogger()->info(sprintf(
            'Purged existing events/relations that used event_source: %s',
            self::EVENT_IMPORT_IDENTIFIER
        ));
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
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
