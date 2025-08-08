<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;

/**
 * DEPRECATED
 *   But I am using this for reference
 *
 * For convenience, take a $class array and fill it with the bare
 * necessities such as students, teachers, rooms, scheduled instances
 */
class ClassCompiler
{
    /**
     * Constructor.
     *
     * You can reuse this instance to precompile multiple classes
     * without having to reinstantiate
     *
     * @param IApi   $api
     * @param string $scope
     */
    public function __construct(private IApi $api, private string $scope)
    {
    }


    /**
     * Precompile a class with its students, teachers and scheduled instances
     *
     * Note that compileSchedule() is very costly, due to latency to the US
     *
     * @param array $class
     * @return array
     */
    public function compileClass(array $class): array
    {
        /* TODO remove dates and replace with actual ones using PAST_ and FUTURE_ thresholds
         *  ~2016 is the only year with data in the sandbox environment
         */
        $startDate = '2016-08-01';
        $endDate = '2016-09-01';

        return [
            'name' => $class['description'],
            'code' => $class['class_id'],
            'students' => $this->compileStudents($class),
            'teachers' => [
                [
                    'id' => $class['primary_teacher']['id'],
                    'name' => $class['primary_teacher']['name']
                ],
            ],
            'schedule' => $this->compileSchedule($class, $startDate, $endDate)
        ];
    }


    /**
     * Find all the students enrolled in $class
     *
     * @param $class
     * @return array
     */
    private function compileStudents($class): array
    {
        $enrolments = [];

        /* TODO This can be busted out into a mega prefetch lookup
         *  to save precious seconds to the US
         */
        $enrolmentsGenerator = $this->api->retrieve('GET', 'academics/enrollments', $this->scope, [
            'internal_class_id' => $class['id']
        ]);

        foreach ($enrolmentsGenerator as $enrolmentsPage) {
            foreach ($enrolmentsPage['data'] as $enrolment) {
                /* Only include people who are currently enrolled
                 */
                if ($enrolment['currently_enrolled']) {
                    $enrolments[] = [
                        'id' => $enrolment['person_id'],
                    ];
                }
            }
        }

        return $enrolments;
    }


    /**
     * Find all the instances of $class and when they're occurring
     * This seems to be known as "Meeting Times" in Veracross
     *
     * Optionally pick two dates as boundaries; otherwise, it just
     * uses today's dates
     *
     * @param array       $class
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    private function compileSchedule(array $class, ?string $startDate = null, ?string $endDate = null): array
    {
        $schedule = [];

        /* Class Times are part of a derived url...you incur a massive
         * penalty making lots of little requests to the US, but there's
         * no endpoint that allows you to prefetch everything
         */
        $endpoint = sprintf(
            'academics/classes/%s/meeting_times',
            $class['id']
        );

        /* TODO remove dates and replace with actual ones using PAST_ and FUTURE_ thresholds
         *  ~2016 is the only year with data in the sandbox environment
         */
        $classMeetingTimesGenerator = $this->api->retrieve('GET', $endpoint, $this->scope, [
            'on_or_after_date' => $startDate ?? date('Y-m-d'),
            'on_or_before_date' => $endDate ?? date('Y-m-d'),
        ]);

        /* Massive performance penalty
         */
        foreach ($classMeetingTimesGenerator as $classMeetingTimesPage) {
            foreach ($classMeetingTimesPage['data'] as $meetingTime) {
                /* Skip timeless records
                 *
                 * TODO These might be "all day" instances of something or other...
                 *  not sure what these really mean in Veracross. A Homeroom class
                 *  usually doesn't go all day for example
                 */
                if (('' === trim((string)$meetingTime['start_time'])) || ('' === trim((string)$meetingTime['end_time']))) {
                    continue;
                }

                /* Trim away the leading 1900-01-01
                 * Why can't people just use the full datetime like normal humans?
                 */
                $startDate = $meetingTime['date'] . substr($meetingTime['start_time'], 10);
                $endDate = $meetingTime['date'] . substr($meetingTime['end_time'], 10);

                /* Clear out meaningless id 0
                 */
                $room = [
                    'id' => null,
                    'name' => null
                ];

                if ($meetingTime['room']['id']) {
                    $room = [
                        'id' => $meetingTime['room']['id'],
                        'name' => $meetingTime['room']['description']
                    ];
                }

                /* Pile it into the stack
                 */
                $schedule[] = [
                    'day' => [
                        'id' => $meetingTime['day']['id'],
                        'name' => $meetingTime['day']['description']
                    ],
                    'start' => $startDate,
                    'end' => $endDate,
                    'room' => $room
                ];
            }
        }

        return $schedule;
    }
}
