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
     * @param string $activityCode
     * @param string $academicGroupCode
     * @return array
     */
    public function generate(string $activityCode, string $academicGroupCode): array
    {
        $stack = [
            'students' => [],
            'teachers' => []
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

            /* Now mine out a teacher, if specified
             *
             * TODO: There's potential support for Multiple Teachers by inspecting the
             *  'sessions' array and mining out teachers from that, on a per-class instance basis.
             *  That might let you have a different teacher on a given instance of this Activity in
             *  the calendar representation - there doesn't seem to be any of that happening yet tho
             */
            if ('' !== (string)$enrolment['leadTeacherCode']) {
                $stack['teachers'][] = (string)$enrolment['leadTeacherCode'];
            }
        }

        return $stack;
    }
}
