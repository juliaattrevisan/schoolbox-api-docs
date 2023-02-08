<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

/**
 * Compiles the timetable for a specific person, an orthogonal
 * variant to ClassCompiler to support segregated import and so
 * you can view a guy's whole timetable in one go.
 */
class PersonCompiler
{
    /**
     * @var Lookup\ClassIndex
     */
    private Lookup\ClassIndex $classIndexLookup;

    /**
     * @param IApi   $api
     * @param string $scope
     */
    public function __construct(private IApi $api, private string $scope)
    {
        $this->classIndexLookup = new Lookup\ClassIndex($this->api);
    }


    /**
     * Compile $externalId's timetable for classes that occur between
     * $startDate and $endDate
     *
     * @param string $externalId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function compile(string $externalId, string $startDate, string $endDate): array
    {
        $classIndexLookup = $this->classIndexLookup->generate();

        $enrolments = [];

        /* TODO This can be busted out into a mega prefetch lookup
         *  to save precious seconds to the US
         */
        $enrolmentsGenerator = $this->api->retrieve('GET', 'academics/enrollments', Classes::SCOPE, [
            'person_id' => $externalId
        ]);

        foreach ($enrolmentsGenerator as $enrolmentsPage) {
            foreach ($enrolmentsPage['data'] as $enrolment) {
                /* Only include people who are currently enrolled
                 */
                if ($enrolment['currently_enrolled']) {
                    /* See if this class exists in the index
                     */
                    $class = $classIndexLookup[$enrolment['internal_class_id']] ?? null;

                    /* Unknown class? Just leave
                     * Veracross permits orphaned data
                     */
                    if (null === $class) {
                        continue;
                    }

                    /* Augment each class with its scheduled instances
                     */
                    $enrolments[] = [
                        'id' => $enrolment['person_id'],
                        'internal_class_id' => $class,
                        'schedule' => $this->compileSchedule($class, $startDate, $endDate)
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
     * @param array  $class
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    private function compileSchedule(array $class, string $startDate, string $endDate): array
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