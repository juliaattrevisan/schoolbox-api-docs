<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Timetable;

use Alaress_Date_Format;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Data generator for a class' enrolment from Compass.
 *
 * Looks up a lead teacher, enrolled students and individual session teachers
 * for each subject from `schedule/v1/GetStandardClassDetails`.
 *
 * This generator makes no special assumptions about the data structure returned
 * by Compass.
 */
class Enrolment
{
    /**
     * API for communicating with Compass.
     */
    private IApi $api;

    /**
     * Constructor
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }

    /**
     * Generates a set of teachers and students for this academic group code and
     * activity code.
     *
     * @param string $academicGroupCode
     * @param string $activityCode
     * @return array[] students: a list of student codes
     *                 teachers: a list of teacher codes
     */
    public function generate(string $academicGroupCode, string $activityCode): array
    {
        // Prebuild periods from structure
        $classDetails = $this->api->retrieveDocument(
            'schedule/v1/GetStandardClassDetails',
            ['activityCode' => $activityCode, 'academicGroupCode' => $academicGroupCode]
        );

        // Only things we need to know:
        $teachers = [];
        $students = [];
        foreach ($classDetails['d'] as $classDetail) {
            $leadTeacherCode = $classDetail['leadTeacherCode'] ?? null;
            if (isset($leadTeacherCode)) {
                $teachers[$leadTeacherCode] = $leadTeacherCode;
            }
            foreach ($classDetail['enrolments'] as $enrolment) {
                // Only enrol the student if:
                // * their enrolment has started
                // * their enrolment has not yet finished

                // Check their start date:
                // if it's in the future, do not enrol them
                if (isset($enrolment['enrolmentStartDate'])) {
                    $enrolmentStartDate = Alaress_Date_Format::newFromTimestamp(strtotime($enrolment['enrolmentStartDate']));
                    $isEnrolmentStarted = !$enrolmentStartDate->isFutureDate();
                } else {
                    // If start date isn't provided: assume the start date is
                    // some unspecified past date
                    // (and thus they have started)
                    $isEnrolmentStarted = true;
                }

                // Check their finish date:
                // if it's in the past, do not enrol them
                if (isset($enrolment['enrolmentFinishDate'])) {
                    $enrolmentFinishDate = Alaress_Date_Format::newFromTimestamp(strtotime($enrolment['enrolmentFinishDate']));
                    $isEnrolmentFinished = $enrolmentFinishDate->isPastDate();
                } else {
                    // if finish date isn't provided, assume the finish date is
                    // an unspecified future date
                    // (and thus they have not finished)
                    $isEnrolmentFinished = false;
                }

                // Enrol the student in this class if their enrolment is current
                // (has started, and not finished)
                if ($isEnrolmentStarted && !$isEnrolmentFinished) {
                    $studentCode = $enrolment['student']['code'] ?? null;
                    if (isset($studentCode)) {
                        $students[$studentCode] = $studentCode;
                    }
                }
            }

            // Assume for now that every teacher for every session is considered
            // a teacher for this class.
            // (Potentially we could attempt to only set a single teacher who's
            //  actually teaching each class? `sis_class_period` has a single
            //  `teacher_external_id` column.)
            foreach ($classDetail['sessions'] as $session) {
                foreach ($session['teachers'] as $teacher) {
                    $teacherCode = $teacher['code'] ?? null;
                    if (isset($teacherCode)) {
                        $teachers[$teacherCode] = $teacherCode;
                    }
                }
            }
        }

        return [
            'teachers' => $teachers,
            'students' => $students,
        ];
    }
}
