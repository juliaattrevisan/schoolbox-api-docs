#!/usr/bin/php
<?php
/**
 * Calendar CSV import tool.  Use to import calendar CSV files from the command line or via cron
 * Use the source name to continually override existing calendar source sets
 *
 * @author      Matt Schembri <matt.schembri@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;

require __DIR__ . '/../../conf/core/require.php';

// ROUTING APP
$kernel = new CliTool\Kernel(
    new class extends CliTool\App
    {

        private const OPT_LONG_HELP = 'help';         // print help
//    --file:       CSV file that matches the schoolbox calendar template located in Admin->Calendar
        private const OPT_LONG_FILE = 'file:';
//    --owner:      The user ID of who the events should be owned by
        private const OPT_LONG_OWNER = 'owner:';
//    --campus:     Comma-seperated list of campus IDs that the events should be shown to
        private const OPT_LONG_CAMPUS = 'campus:';
//    --role:       Comma-seperated list of role IDs that the events should be shown to
        private const OPT_LONG_ROLE = 'role:';
//    --source:     Optional source name, subsequent uploads will delete any events with the same source name
        private const OPT_LONG_SOURCE = 'source:';

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

        public function run(): void
        {
            $log = $this->getLogger();
            $file = null;
            if ($this->getOpt(self::OPT_LONG_HELP)) {
                echo <<<HELP
Usage: calendarImport.php --file import.csv --owner=1 --campus=1,2 --role=1 [--source='sourcename']
    --file:       CSV file that matches the schoolbox calendar template located in Admin->Calendar
    --owner:      The user ID of who the events should be owned by
    --campus:     Comma-seperated list of campus IDs that the events should be shown to
    --role:       Comma-seperated list of role IDs that the events should be shown to
    --source:     Optional source name, subsequent uploads will delete any events with the same source name
    
HELP;
                exit;
            } else {
                if (!$this->getOpt(self::OPT_LONG_CAMPUS)) {
                    $log->error("No campus specified. (Use --help for example usage)\n");
                    exit(1);
                } else {
                    $campusList = explode(',', $this->getOpt(self::OPT_LONG_CAMPUS));
                }

                if (!$this->getOpt(self::OPT_LONG_ROLE)) {
                    $log->error("No role specified. (Use --hhelp for example usage)\n");
                    exit(1);
                } else {
                    $groupList = explode(',', $this->getOpt(self::OPT_LONG_ROLE));
                }

                if (!$this->getOpt(self::OPT_LONG_OWNER)) {
                    $log->error("No owner id specified. (Use --help for example usage)\n");
                    exit(1);
                } else {
                    $ownerId = $this->getOpt(self::OPT_LONG_OWNER);
                }

                if (!$this->getOpt(self::OPT_LONG_FILE) && file_exists($this->getOpt(self::OPT_LONG_FILE))) {
                    $log->error("Specified file not found. (Use --help for example usage)\n");
                    exit(1);
                } else {
                    $file = $this->getOpt(self::OPT_LONG_FILE);
                }
            }

            //Optional options
            if (!$this->getOpt(self::OPT_LONG_SOURCE)) {
                $source = $this->getOpt(self::OPT_LONG_SOURCE);
                $log->warning('Events with source "' . $source . '" will be deleted' . "\n");
            } else {
                $source = null;
            }

            //Not used in this context
            $categoryList = [];

            $bulkHandler = new Bulk_Calendar($file, $source, $ownerId, $categoryList, $groupList, $campusList);

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
    },
    false
);

$kernel->runApp();