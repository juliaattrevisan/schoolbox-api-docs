<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Get a set of student external ids and a set of teacher external ids,
 * for the given (activityCode, academicGroupCode) tuple
 */
class Enrolments
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * API for communicating with Compass.
     */
    private IApi $api;


    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param IApi            $api
     */
    public function __construct(LoggerInterface $log, IApi $api)
    {
        $this->log = $log;
        $this->api = $api;
    }


    /**
     * Generate a set of enrolments for the given (activityCode, academicGroupCode) tuple
     *
     * $activityCode is the $activity[name] when you look inside a Schedule's list of Activities;
     * oddly, there's importIdentifier and activityId, but neither of those should be used for this.
     *
     * $academicGroupCode was derived earlier from your Academic Groups lookup. It usually corresponds
     * to a year.
     *
     * Additional teachers of a class are found in this Sessions array node, which we need elsewhere
     *
     * @param string $activityCode
     * @param string $academicGroupCode
     * @return array
     */
    public function generate(string $activityCode, string $academicGroupCode): array
    {
        $stack = [
            'students' => [],
            'sessions' => [],
        ];

        /* Get a list of all the guys involved in this $activity
         * It's a bunch of students and teachers
         */
        $enrolments = $this->api->retrieveDocument('schedule/v1/GetStandardClassDetails', [
            'activityCode' => $activityCode,
            'academicGroupCode' => $academicGroupCode
        ]);

        /* Inspect each $student to see whether they should actually be put
         * into this event's relationships
         */
        foreach ($enrolments['d'] as $enrolment) {
            foreach ($enrolment['enrolments'] as $student) {
                if ('' !== (string)$student['enrolmentStartDate']) {
                    /* Their enrolmentStartDate must be in the past
                     */
                    $enrolmentAlreadyStarted = \Alaress_Date_Format::newFromTimestamp(strtotime($student['enrolmentStartDate']))->isPast();
                } else {
                    /* or, indeterminate beginning; assume they are enrolled
                     */
                    $enrolmentAlreadyStarted = true;
                }

                if ('' !== (string)$student['enrolmentfinishDate']) {
                    /* Their enrolmentFinishDate must be in the future
                     * Yes, it's a lowercase f. Disgusting
                     */
                    $enrolmentNotEnded = \Alaress_Date_Format::newFromTimestamp(strtotime($student['enrolmentfinishDate']))->isFuture();
                } else {
                    /* or, indeterminate end; assume they are enrolled
                     */
                    $enrolmentNotEnded = true;
                }

                /* If they fulfil the enrolment date criteria, stack up their externalId
                 * Otherwise, continue
                 */
                if ($enrolmentAlreadyStarted && $enrolmentNotEnded) {
                    $stack['students'][] = (string)$student['student']['code'];
                }
            }

            /* Augment $stack with a bunch of teachers, believe it or not
             *
             * Additional teachers of a class are found in Sessions which
             * is somehow found here. We need to combine these sessions
             * with the activity instances that are running, in order to
             * find all the teachers of a class (jesus)
             */
            $sessions = $enrolment['sessions'];

            /* sort sessions ffs, because instances occur in a random haphazard order
             * but are actually chronologically ordered
             */
            usort($sessions, static function ($a, $b) {
                if ($a['start'] < $b['start']) {
                    return -1;
                }

                if ($a['start'] === $b['start']) {
                    return 0;
                }

                return 1;
            });

            /* Ram it in here with everything else
             */
            $stack['sessions'] = $sessions;
        }

        return $stack;
    }
}
