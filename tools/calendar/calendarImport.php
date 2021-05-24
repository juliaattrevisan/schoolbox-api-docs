#!/usr/bin/php
<?php

/**
 * Calendar CSV import tool.  Use to import calendar CSV files from the command line or via cron
 * Use the source name to continually override existing calendar source sets
 *
 * @author      Matt Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

// ROUTING APP
$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {

        // --help: print help
        private const OPT_LONG_HELP = 'help';
        // --file:       CSV file that matches the schoolbox calendar template located in Admin->Calendar
        private const OPT_LONG_FILE = 'file:';
        // --owner:      The user ID of who the events should be owned by
        private const OPT_LONG_OWNER = 'owner:';
        // --campus:     Comma-separated list of campus IDs that the events should be shown to
        private const OPT_LONG_CAMPUS = 'campus:';
        // --role:       Comma-separated list of role IDs that the events should be shown to
        private const OPT_LONG_ROLE = 'role:';
        // --source:     Optional source name, subsequent uploads will delete any events with the same source name
        private const OPT_LONG_SOURCE = 'source:';


        /** @var string|null */
        private $file;

        /** @var string|null */
        private $ownerId;

        /** @var array|null */
        private $campusList;

        /** @var array|null */
        private $roleList;

        /** @var string|null */
        private $source;

        /** @inheritdoc */
        public function getLongOpts(): array
        {
            return [
                self::OPT_LONG_HELP,
                self::OPT_LONG_FILE,
                self::OPT_LONG_OWNER,
                self::OPT_LONG_CAMPUS,
                self::OPT_LONG_ROLE,
                self::OPT_LONG_SOURCE,
            ];
        }


        /** @inheritdoc */
        public function run(): void
        {
            $log = $this->getLogger();

            if ($this->getOpt(self::OPT_LONG_HELP) || !$this->parseAppOptions()) {
                echo <<<HELP

Calendar CSV import tool. Use to import calendar CSV files from the command line or via cron
Use the source name to continually override existing calendar source sets

Usage: calendarImport.php --file import.csv --owner=1 --campus=1,2 --role=1 [--source='sourcename']
    --file:       CSV file that matches the schoolbox calendar template located in Admin->Calendar
    --owner:      The user ID of who the events should be owned by
    --campus:     Comma-separated list of campus IDs that the events should be shown to
    --role:       Comma-separated list of role IDs that the events should be shown to
    --source:     Optional source name, subsequent uploads will delete any events with the same source name


HELP;
                exit;
            }

            $bulkHandler = new Bulk_Calendar($this->file, $this->source, $this->ownerId, [], $this->roleList, $this->campusList);

            try {
                $result = $bulkHandler->addCalendar();
                foreach ($result as $event) {
                    $log->notice("{$event['status']} event: {$event['name']}, {$event['error']}");
                }
            } catch (Schoolbox\Mapper\Exception $e) {
                $log->error("User or campus(es) not found unable to load events.\n");
                exit(1);
            } catch (PDOException $e) {
                $log->error("One of the supplied roles was not found, unable to load events.\n");
                exit(1);
            }
        }

        /**
         * Parses options and returns whether all options are valid
         *
         * @return bool
         */
        private function parseAppOptions(): bool
        {
            $valid = true;

            if (!$this->getOpt(self::OPT_LONG_CAMPUS)) {
                $this->getLogger()->error('No campus specified.');
                $valid = false;
            } else {
                $this->campusList = explode(',', $this->getOpt(self::OPT_LONG_CAMPUS));
            }

            if (!$this->getOpt(self::OPT_LONG_ROLE)) {
                $this->getLogger()->error('No role specified.');
                $valid = false;
            } else {
                $this->roleList = explode(',', $this->getOpt(self::OPT_LONG_ROLE));
            }

            if (!$this->getOpt(self::OPT_LONG_OWNER)) {
                $this->getLogger()->error('No owner id specified.');
                $valid = false;
            } else {
                $this->ownerId = $this->getOpt(self::OPT_LONG_OWNER);
            }

            if (!$this->getOpt(self::OPT_LONG_FILE) || !file_exists($this->getOpt(self::OPT_LONG_FILE))) {
                $this->getLogger()->error('Specified file not found.');
                $valid = false;
            } else {
                $this->file = $this->getOpt(self::OPT_LONG_FILE);
            }

            /* source: optional flag
             * The source column allows us to identify events with an arbitrary string ("source") so we can
             * see where events came from (ie. what imported them). It only has utility when you specify a value
             * It's optional, but if you supply it, it must have a value
             * Currently, this flag is ignored, when a value is not provided.
             */
            if ($this->getOpt(self::OPT_LONG_SOURCE)) {
                $this->source = $this->getOpt(self::OPT_LONG_SOURCE);
                $this->getLogger()->warning("Events with source '{$this->source}' will be deleted.\n");
            }

            return $valid;
        }
    }
);

$kernel->runApp();
