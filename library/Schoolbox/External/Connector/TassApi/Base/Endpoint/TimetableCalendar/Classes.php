<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Import all the timetables for everyone.
 */
class Classes implements IStrategy
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var array
     */
    private array $api;


    /**
     * @var Lookup
     */
    private Lookup $lookup;


    /**
     * @var array
     */
    private array $timetableCalendar = [];


    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param array           $api
     * @param Lookup          $lookup
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        array $api,
        Lookup $lookup
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->lookup = $lookup;
    }


    /**
     * Import all timetables for everyone
     *
     * @return void
     */
    public function import(): void
    {
        $this->importStudentTimetables();
    }


    /**
     * Import all student timetables.
     *
     * @return void
     */
    private function importStudentTimetables(): void
    {
        /* Students
         * Find all the external ids
         */
        $students = $this->lookup->getStudents();

        /* Inspect every student's timetable to find the largest quantity of Periods per Day
         * This will inform schoolbox's classic idea of a Timetable Definition which must be
         * rectangular; every day will have the maximum number of periods in it, even if the
         * student's timetable doesn't use it
         */
        foreach ($students as $externalId => $person) {
            /* Hit That Api to find every student's timetable and cache it somewhere
             * We'll need to operate on it all again later anyway
             */
            $data = $this->api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getStudentTimetable', [
                'student_code' => $externalId
            ]);

            /* Check that the given $externalId had any timetable data at all
             */
            if (!(isset($data['student_timetable']) && \count($data['student_timetable']))) {
                $this->log->warning(sprintf(
                    'Timetable not found for %s (%s).',
                    trim("{$person['firstname']} {$person['lastname']}"),
                    $externalId
                ));

                continue;
            }

            /* Otherwise, continue on with the first timetable element
             * (Not sure how many they can actually have in production)
             */
            $timetable = $data['student_timetable'][0];

            /* Example timetable extract

                Array
                (
                    [HSE_CODE] => A
                    [DESC_TEXT] => Secondary School Timetable 2022/1
                    [HSE_NAME] => Ashkanasy
                    [DAYS] => Array
                        (
                            [11] => Array
                                (
                                    [PERIODS] => Array
                                        (
                                            [11] => Array
                                                (
                                                    [SUBJECTS] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [SUB_CODE] => 1224
                                                                    [FROM_DATE] =>
                                                                    [SUB_SHORT] => RE
                                                                    [HASOVERRIDE] => 0
                                                                    [ROOM_CODE] => 504
                                                                    [TO_DATE] =>
                                                                    [TCH_CODE] => RIC
                                                                    [SUBTAB_YEAR_GRP_DESC] => 12
                                                                    [CLASS] => D
                                                                    [YEAR_GRP] =>
                                                                    [TT_PRIORITY] => 999
                                                                    [SUB_DESC] => Unit 3&4 Religion & Society
                                                                    [SUB_ABREV] => RE
                                                                    [LINE_CODE] => 12
                                                                    [SUBTAB_YEAR_GRP] => 12
                                                                    [DATE_RANGE] =>
                                                                )

                                                        )

                                                    [PRD_END] => 1900-01-01 15:30:00.0
                                                    [PRD_CODE] => 11
                                                    [PRD_START] => 1900-01-01 14:38:00.0
                                                    [PRD_DESC] => Period 6
                                                )

                                            [2] => Array
                                                (
                                                    [SUBJECTS] => Array
                                                        (
                                                            [0] => Array
                                                                (
                                                                    [SUB_CODE] => 1218
                                                                    [FROM_DATE] =>
                                                                    [SUB_SHORT] => MC
                                                                    [HASOVERRIDE] => 0
                                                                    [ROOM_CODE] => 131
                                                                    [TO_DATE] =>
                                                                    [TCH_CODE] => PEA
                                                                    [SUBTAB_YEAR_GRP_DESC] => 12
                                                                    [CLASS] => C
                                                                    [YEAR_GRP] =>
                                                                    [TT_PRIORITY] => 999
                                                                    [SUB_DESC] => Yr 12 Mechanech
                                                                    [SUB_ABREV] => MC
                                                                    [LINE_CODE] => 12
                                                                    [SUBTAB_YEAR_GRP] => 12
                                                                    [DATE_RANGE] =>
                                                                )

                                                        )

                                                    [PRD_END] => 1900-01-01 08:59:00.0
                                                    [PRD_CODE] => 2
                                                    [PRD_START] => 1900-01-01 08:50:00.0
                                                    [PRD_DESC] => Form Assembly
                                                )
            */

            $this->process($timetable);
        }
    }


    /**
     * Create individual SUDS Timetable events out of each eleemnt
     * inside this person's $timetable
     *
     * @param array $timetable
     * @return void
     */
    private function process(array $timetable): void
    {
        $timetableCalendar = $this->fetchTimetableCalendar($timetable['TT_ID']);
        print_r($timetable);
        die();
        print_r($timetableCalendar);
        die();


        /* Timetable Calendar gives us a list of Days and Dates
         * Each entry in there represents a day in the timetable,
         * which contains multiple periods
         */

        /* For each entry in Timetable Calendar, set up a series of SUDS Timetable records
         * You can get a date here, but you'll need to make multiple records.
         *
         * To do it,
         * For each entry in Timetable Calendar, get the Day Code
         * then use that to look up the corresponding struct in $timetable
         * This will get us a list of Periods happening in that day,
         * and their times
         */

            /* For each entry in $timetable[$day], create a new SUDS Timetable event
             * Use the date that we got from the outer loop
             * Combine it with the time in the current array element
             * Save it
             */

            /* Create a new involvement for this event if one doesn't already exist
             * (This superpositions on top of Additional Subjects)
             */

    }


    /**
     * Get the Timetable Calendar for a particular TTID
     * if we haven't already done so
     *
     * We use this to find out the date/day combo and match
     * it with periods. It's essentially half of the classical
     * timetable grid Definition; the other half comes from
     * the timetable itself, which is a list of periods and their
     * times/days (but not their actual dates)
     *
     * So we have to marry the two datasets into SUDS Timetable
     *
     * @param int $ttid
     * @return mixed
     */
    private function fetchTimetableCalendar(int $ttid): array
    {
        if (!isset($this->timetableCalendar[$ttid])) {
            $this->timetableCalendar[$ttid] = $this->api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTimetableCalendar', [
                'tt_id' => $ttid
            ]);
        }

        /* Example extract
            [tt_calendar] => Array
                (
                    [0] => Array
                        (
                            [CAT_NUM] =>
                            [CMPY_CODE] => 01
                            [TTDATE] => 2022-02-01 00:00:00.0
                            [RECORD_ID] => 22324
                            [LABEL] => Tue 01/02/2022
                            [ID] => 63-2022-02-01
                            [TABLE_ID] => ttcalendar
                            [VALUE] => 2022-02-01
                            [TT_ID] => 63
                            [DAY_CODE] => 2
                        )

                    [1] => Array
                        (
                            [CAT_NUM] =>
                            [CMPY_CODE] => 01
                            [TTDATE] => 2022-02-02 00:00:00.0
                            [RECORD_ID] => 22325
                            [LABEL] => Wed 02/02/2022
                            [ID] => 63-2022-02-02
                            [TABLE_ID] => ttcalendar
                            [VALUE] => 2022-02-02
                            [TT_ID] => 63
                            [DAY_CODE] => 3
                        )
         */

        return $this->timetableCalendar[$ttid]['tt_calendar'];
    }
}