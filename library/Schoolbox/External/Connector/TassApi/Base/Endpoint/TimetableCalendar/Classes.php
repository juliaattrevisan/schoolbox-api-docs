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

        return $this->timetableCalendar[$ttid]['tt_calendar'];
    }
}