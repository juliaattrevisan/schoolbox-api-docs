<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;

/**
 * Fetches everyone's timetable from tass and remembers them
 */
class Preloader
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
     * Get all the students' timetables, which we use to divine a schoolbox Timetable Definition
     * In tass, students can have seemingly unique timetables with unique definitions (there isn't
     * really an equivalent of Timetable Definition in tass) so we have to infer something similar
     * as best we can
     *
     * @throws \DomainException
     * @throws \RuntimeException
     */
    private function preloadAllTimetables(): void
    {

        /* Students
         * Find all the external ids
         */
        $students = $this->lookup->getStudents();

        /* Staff
         * Find all the external ids
         */
        $staff = $this->lookup->getStaff();

        /* Remind people this might take a while
         */
        $this->log->info(sprintf(
            'Preloading timetables for %s students and %s staff - this might take a while',
            \count($students),
            \count($staff)
        ));

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

            $this->preloadTimetable((string)$externalId, $person, $data, 'student_timetable');
        }

        /* Make a naive guess as to what year and semester we are in
         * The getTeacherTimetable endpoint is different to students' version
         */
        $year = date('Y');

        $semester = $this->detectSemester($this->api[DSN::MODULE_LMS_INTEGRATION]->getOptions());

        /* Inspect every staff's timetable
         */
        foreach ($staff as $externalId => $person) {
            $data = $this->api[DSN::MODULE_LMS_INTEGRATION]->retrieveDocument('getTeacherTimetable', [
                'teacher_code' => $externalId,
                'year_num' => $year,
                'semester' => $semester
            ]);

            $this->preloadTimetable((string)$externalId, $person, $data, 'teacher_timetable');
        }
    }

    /**
     * Fetch the timetable data for user $externalId
     *
     * This algorithm is the same for students and staff and the data structures are
     * mostly synonymous, except for a $key name being different and the $data is
     * fetched with some different params
     *
     * @param string $externalId
     * @param array  $person
     * @param array  $data
     * @param string $key
     */
    private function preloadTimetable(string $externalId, array $person, array $data, string $key): void
    {
        /* Check that the given $externalId had any timetable data at all
         */
        if (!(isset($data[$key]) && \count($data[$key]))) {
            $this->log->warning(sprintf(
                'Timetable not found for %s (%s).',
                trim("{$person['firstname']} {$person['lastname']}"),
                $externalId
            ));

            return;
        }

        /* Otherwise, continue on with the first timetable element
         * (Not sure how many they can actually have in production)
         */
        $timetable = $data[$key][0];

        /* Remember each student's timetable (we'll come back to this later)
         */
        $this->timetableRegistry->remember($externalId, $timetable);
    }
}