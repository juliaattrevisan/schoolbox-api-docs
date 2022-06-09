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
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP
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

This ALWAYS EXECUTES a soft-delete of tass calendar events.

This removes all the old suds_tass events which our previous importer put events into
the schoolbox.event table, and all its relational pieces. This has since moved to
SUDS Events and is no longer required here where it pollutes the event space.

This always runs in perform mode as part of the v22 update and is a reimplementation
of Tass Calendar Prepare.

Usage: php 00-purge-old-tasscalendar.php --perform


HELP;
            exit;
        }

        /* Check if you're running SUDS TASS first
         *
         * If you are, continue deleting the event records associated with a suds_tass import
         * (these should be loaded into SUDS Events instead of schoolbox.events, separately)
         *
         * Otherwise, don't do anything and just exit politely
         * This script had the potential to cross-over into compass calendar which also uses the same
         * concept of setting potentially-deleteable events' updated_at to 0000-00-00
         */
        if (\Schoolbox\External\Importer\Endpoint\Factory::TASS_API !== $this->getConfig()->get('schooldata_import_data_source')) {
            $this->getLogger()->info('TASS Calendar purge did not run: this schoolbox instance did not use SUDS TASS. No changes required; exiting');
        }

        $eventMapper = \Schoolbox\Calendar\EventMapper::getInstance();
        $eventSourceMapper = \Event\Source\Mapper::getInstance();
        $source = \Schoolbox\External\Connector\TassApi\Base\Endpoint\Calendar\Factory::SOURCE_NAME;

        $query = "
                UPDATE
                {$eventSourceMapper->getTable()}
                INNER JOIN {$eventSourceMapper->getJoinSql('event')}
                AND {$eventSourceMapper->getTable()}.`deleted_at` IS NULL
                SET {$eventMapper->getTable()}.updated_at = '0000-00-00 00:00:00',
                {$eventSourceMapper->getTable()}.updated_at = '0000-00-00 00:00:00'
                WHERE {$eventSourceMapper->getTable()}.`source_name` IN (:sourceName, 'ParentCalendar', 'ParentNotices', 'StudentCalendar', 'StudentNotices', 'TeacherCalendar', 'TeacherNotices')
            ";

        $statement = $eventSourceMapper->getDatabase()->prepare($query);
        $statement->execute([
            'sourceName' => $source
        ]);

        $this->getLogger()->debug(sprintf(
            'Marking all %s events for deletion',
            $source
        ));

        $deadEvents = $eventMapper->findByColumn('updated_at', '0000-00-00 00:00:00');

        foreach ($deadEvents as $deadEvent) {
            $eventMapper->delete($deadEvent);
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
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
