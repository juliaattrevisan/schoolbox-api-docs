<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\Loader;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Api;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\Code;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar\SemesterDetector;

require_once __DIR__ . '/../../../../conf/core/require.php';

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
     * Make sure to add your locking script to the Lock Inspector tool
     * const LOCK_NAMES in Inspector.php
     */
    public const LOCK_NAME = 'SUDS TASS Timetable Analysis';


    /**
     * --help
     *
     * Print help text for those who don't know how to use this tool
     */
    private const OPT_LONG_HELP = 'help';


    /**
     * --externalId
     *
     * A person's externalId, whose timetable you want to examine
     */
    private const OPT_LONG_EXTERNAL_ID = 'externalId:';


    /**
     * --semester
     *
     * Specify an arbitrary int to see what was in that semester
     * Omitting this cause the tool to examine dynamically based on
     * the TASS Academic Periods values in admin
     */
    private const OPT_LONG_SEMESTER = 'semester:';


    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_EXTERNAL_ID,
            self::OPT_LONG_SEMESTER
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

SUDS TASS Timetable Analysis

* Find out what (day, period) tuples are expected for the provided staff member
denoted by externalId
* Find out what timetable id, timetable dates, etc. are covered by the
TASS Academic Period values, or the --semester option if provided.

This does not change any data; it only reports what it finds.

Usage: timetable.php --externalId=... [--semester=...]

Example: timetable.php --externalId=DE
Example: timetable.php --externalId=DE --semester=3


HELP;
            return;
        }

        $this->getLogger()->info('SUDS TASS Staff Timetable Analysis');
        $this->getLogger()->info('');

        /* From the CLI
         */
        $externalId = trim((string)$this->getOpt(self::OPT_LONG_EXTERNAL_ID));
        $semester = trim((string)$this->getOpt(self::OPT_LONG_SEMESTER));

        /* These are parts harvested directly from TassApi's Classes.php
         * lovingly re-enacted here
         */
        $host = $this->getConfig()->get('schooldata_import_endpoint_host');
        $key = $this->getConfig()->get('schooldata_import_endpoint_key');
        $api[DSN::MODULE_LMS_INTEGRATION] = new Api($host, $key, DSN::MODULE_LMS_INTEGRATION);

        if ('' === $semester) {
            /* Calculate semseter from TASS Academic Periods settings
             */
            $dateLocalFormat = $this->getConfig()->get('date_local');

            $options = $api[DSN::MODULE_LMS_INTEGRATION]->getOptions();

            $semester = (new SemesterDetector($this->getLogger(), $dateLocalFormat))->detect($options);

            $this->getLogger()->info(sprintf(
                'We are currently in semester %s according to the TASS Academic Periods',
                $semester
            ));

            /* Explore the set
             */
            echo "\n";

            print_r([
                'TASS Academic Period 1 start date' => $options['tap1start'],
                'TASS Academic Period 2 start date' => $options['tap2start'],
                'TASS Academic Period 3 start date' => $options['tap3start'],
                'TASS Academic Period 4 start date' => $options['tap4start'],
            ]);
        } else {
            /* Use whatever was decided by the human on the cli
             */
            $this->getLogger()->info(sprintf(
                'We are currently in semester %s as specified via the cli flag',
                $semester
            ));
        }

        echo "\n";

        /* Grab some info from tass land
         */
        $structs = $this->getTimetableStruct($api, $externalId, (int)$semester);
        $calendar = $this->getTimetableCalendar($api, $structs);

        /* Calendar dates encompassed by that person's timetable
         */
        foreach ($calendar as $ttid => $vals) {
            echo sprintf(
                "\nTimetable Id %s encompasses the following date(s):\n",
                $ttid
            );

            print_r($vals);
            echo "\n\n";
        }

        /* Timetable structure for the guy you specified
         */
        echo sprintf(
            "\nThis is the basic timetable structure for user %s using raw tass data\n",
            $externalId
        );

        print_r($structs);
    }


    /**
     * Get a list of dates covered by the provided $ttid
     *
     * @param array $api
     * @param array $structs
     * @return array
     */
    private function getTimetableCalendar(array $api, array $structs): array
    {
        $dates = [];

        /* Timetable Calendar gives us a list of Days and Dates
         * Each entry in there represents a day in the timetable,
         * which contains multiple periods
         */
        foreach ($structs as $struct) {
            $timetableCalendar = $api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTimetableCalendar', [
                'tt_id' => $struct['tt_id']
            ]);

            foreach ($timetableCalendar['tt_calendar'] as $data) {
                $dates[$struct['tt_id']][$data['VALUE']] = $data['LABEL'];
            }
        }

        return $dates;
    }


    /**
     * Preview what the timetable structure might be,
     * according to tass data with minimal manipulation from us
     *
     * @param array  $api
     * @param string $externalId
     * @param int    $semester
     * @return array
     */
    private function getTimetableStruct(array $api, string $externalId, int $semester): array
    {
        /* Make a naive guess as to what year and semester we are in
         * The getTeacherTimetable endpoint is different to students' version
         * because reasons - why the students endpoint doesn't need this is beyond me
         */
        $year = date('Y');

        /* Hit That Api to find every student's timetable and cache it somewhere
         * We'll need to operate on it all again later anyway
         */
        $data = $api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeacherTimetable', [
            'teacher_code' => $externalId,
            'year_num' => $year,
            'semester' => $semester
        ]);

        $stack = [];

        /* No data for (externalId, semester) tuple
         */
        if (!isset($data['teacher_timetable'])) {
            $this->getLogger()->warning('No timetable data found; check your externalId/semester value');
            exit;
        }

        foreach ($data['teacher_timetable'] as $timetable) {
            $timetableId = "TT_ID {$timetable['TT_ID']}";

            $stack[$timetableId] = [
                'tt_id' => $timetable['TT_ID'],
                'name' => $timetable['DESC_TEXT'],
                'struct' => []
            ];

            foreach ($timetable['DAYS'] as $dayNumber => $periods) {
                $dayId = "Day {$dayNumber}";

                if (!isset($stack[$timetableId]['struct'][$dayId])) {
                    $stack[$timetableId]['struct'][$dayId] = [];
                }

                foreach ($periods['PERIODS'] as $periodData) {
                    $periodId = "Period {$periodData['PRD_CODE']}";

                    /* Extract the useful time from this microsecond format
                     */
                    $startTime = substr($periodData['PRD_START'], 11, 8);
                    $endTime = substr($periodData['PRD_END'], 11, 8);

                    /* For each entry in $timetable[$day], create a new SUDS Timetable event
                     * Use the date that we got from the outer loop
                     * Combine it with the time in the current array element
                     * Save it
                     */

                    /* Multiple periods might be here
                     */
                    $periodNames = [];

                    foreach ($periodData['SUBJECTS'] as $subject) {
                        $yearGroup = (new FirstMeaningfulValue())->find([
                            (string)$subject['YEAR_GRP'],
                            (string)$subject['SUBTAB_YEAR_GRP']
                        ]);

                        /* Index by time
                         */
                        $subjectIndex = sprintf(
                            '%s - %s',
                            $startTime,
                            $endTime
                        );

                        if (isset($periodNames[$subjectIndex])) {
                            $periodNames[$subjectIndex] = [];
                        }

                        /* Then index by class code
                         */
                        $classCode = (new Code())->encode($yearGroup, (string)$subject['SUB_CODE'], (string)$subject['CLASS']);

                        $periodNames[$subjectIndex][$classCode] = $subject['SUB_LONG'];
                    }

                    $stack[$timetableId]['struct'][$dayId][$periodId] = $periodNames;
                }
            }
        }

        return $stack;
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
