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
        $now      = Alaress_Date_Format::now();
        foreach ($classDetails['d'] as $classDetail) {
            $leadTeacherCode = $classDetail['leadTeacherCode'] ?? null;
            if (isset($leadTeacherCode)) {
                $teachers[$leadTeacherCode] = $leadTeacherCode;
            }
            foreach ($classDetail['enrolments'] as $enrolment) {
                // Need to take enrolment start/finish into account
                $enrolmentStartDate = isset($enrolment['enrolmentStartDate'])
                    ? Alaress_Date_Format::newFromTimestamp(strtotime($enrolment['enrolmentStartDate']))
                    : $now;
                $enrolmentFinishDate = isset($enrolment['enrolmentFinishDate'])
                    ? Alaress_Date_Format::newFromTimestamp(strtotime($enrolment['enrolmentFinishDate']))
                    : $now;
                if (
                    ($enrolmentStartDate->isToday() || $enrolmentStartDate->lte($now))
                    && ($enrolmentFinishDate->isToday() || $enrolmentFinishDate->gte($now))
                ) {
                    $studentCode = $enrolment['student']['code'] ?? null;
                    if (isset($studentCode)) {
                        $students[$studentCode] = $studentCode;
                    }
                }
            }
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
