<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Specification
{
    /**
     * Specification constructor.
     *
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     */
    public function check(): array
    {
        /* Sentral endpoints sometimes require other endpoints' results first
         *
         * They vary too much, so I put them into anon functions so they can
         * output the result of multiple api requests synonymously
         */
        $paths = [

            /**
             * Student Profile nodes
             */
            [
                'name' => 'Academic Period: enrolments/academic-period...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $date = date('Y-m-d');

                        $this->api->retrieveDocument("enrolments/academic-period?date={$date}&limit=1");
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],
            [
                'name' => 'Students: enrolments/student...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $this->api->retrieveDocument('enrolments/student?include=person,primaryEnrolment,contacts,specialNeedsPrograms&limit=1');
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],

            /**
             * Parent Profile nodes
             */
            [
                'name' => 'Parents: enrolments/person/...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $students = $this->api->retrieveDocument('enrolments/student?include=person,primaryEnrolment,contacts,specialNeedsPrograms&limit=1');

                        $student = $students['data'][0];

                        $contacts = $student['relationships']['contacts']['data'];

                        $this->api->retrieveDocument("enrolments/person/{$contacts[0]['attributes']['personId']}?include=phoneNumbers");
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],

            /**
             * Staff Profile nodes
             */
            [
                'name' => 'Staff: enrolments/staff...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $staffs = $this->api->retrieveDocument('enrolments/staff?include=person,qualifications,employments&limit=1');

                        $staff = $staffs['data'][0];

                        $this->api->retrieveDocument("enrolments/person/{$staff['relationships']['person']['data']['id']}?include=phoneNumbers");
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],

            /**
             * Timetable nodes
             */
            [
                'name' => 'Timetable Definition Compiler: timetables/timetable-calendar-date...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $monday = date('Y-m-d', strtotime('monday this week'));
                        $friday = date('Y-m-d', strtotime('friday this week'));

                        $this->api->retrieveDocument("timetables/timetable-calendar-date?from={$monday}&to={$friday}&limit=1");
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],
            [
                'name' => 'Timetable Period In Day: timetables/timetable-period-in-day',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $this->api->retrieveDocument("timetables/timetable-period-in-day");
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],
            [
                'name' => 'Student Timetable: timetables/student/.../timetable-lessons...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        /* Brief reimplementation of academic period id retrieval

                         * There is hopefully a student in one of the prescribed academic period ids
                         * who has a timetable, in the first 10 we selected
                         *
                         * Otherwise this might always show something about missing timeviews
                         */
                        $ids = [];
                        $date = date('Y-m-d');

                        $nextUrl = "enrolments/academic-period?date={$date}&limit=200";

                        for ($i = 0; $nextUrl && $i < 3; $i++) {
                            $payload = $this->api->retrieveDocument($nextUrl);

                            foreach ($payload['data'] as $row) {
                                $ids[] = $row['id'];
                            }

                            /* Prepare for next page if it exists
                             */
                            $nextUrl = $payload['links']['next'] ?? null;
                        }

                        /* Pass this as a csv arg
                         */
                        $academicPeriodIds = implode(',', $ids);

                        $monday = date('Y-m-d', strtotime('monday this week'));
                        $friday = date('Y-m-d', strtotime('friday this week'));

                        $students = $this->api->retrieveDocument("enrolments/student?include=person,primaryEnrolment,contacts,specialNeedsPrograms&academicPeriodIds={$academicPeriodIds}&limit=1");

                        /* Guess a 10-person payload
                         * Try to find someone with a timetable; sentral refers to this as a "timeview information"
                         */
                        foreach ($students['data'] as $student) {
                            try {
                                $this->api->retrieveDocument("timetables/student/{$student['id']}/timetable-lessons?fromDate={$monday}&toDate={$friday}");
                                break;
                            } catch (\InvalidArgumentException $e) {
                                $errorMessage = $e->getMessage();
                                $result = false;
                            } catch (\DomainException $e) {
                                /* The current person might not have a timeview (a  timetable)
                                 * but the api endpoint is still there; that's still a success
                                 */
                                continue;
                            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                                /* Couldn't communicate to the endpoint
                                 */
                                $errorMessage = $e->getMessage();
                                $result = false;
                            }
                        }
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],
            [
                'name' => 'Staff Timetable: timetables/staff/.../timetable-lessons...',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $monday = date('Y-m-d', strtotime('monday this week'));
                        $friday = date('Y-m-d', strtotime('friday this week'));

                        $staffs = $this->api->retrieveDocument('enrolments/staff?include=person,qualifications,employments&limit=1');

                        /* Guess a 10-person payload
                         * Try to find someone with a timetable; sentral refers to this as a "registered timeview information"
                         * Yes, this message is slightly different to the students' timetable error
                         */
                        foreach ($staffs['data'] as $staff) {
                            try {
                                $this->api->retrieveDocument("timetables/staff/{$staff['id']}/timetable-lessons?fromDate={$monday}&toDate={$friday}");
                                break;
                            } catch (\InvalidArgumentException $e) {
                                $errorMessage = $e->getMessage();
                                $result = false;
                            } catch (\DomainException $e) {
                                /* The current person might not have a timeview (a  timetable)
                                 * but the api endpoint is still there; that's still a success
                                 */
                                continue;
                            } catch (\GuzzleHttp\Exception\GuzzleException $e) {
                                /* Couldn't communicate to the endpoint
                                 */
                                $errorMessage = $e->getMessage();
                                $result = false;
                            }
                        }
                    } catch (\InvalidArgumentException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result
                    ];
                }
            ],
        ];

        $endpoints = [];

        /* Sentral sometimes requires the results of previous endpoint requests
         */
        foreach ($paths as $path) {
            $message = $path['name'];
            $output = $path['func']();

            $result = $output['result'];
            $errorMessage = $output['errorMessage'];

            if ($result) {
                $endpoints[] = [
                    'message' => $message,
                    'reason' => '',
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $message,
                    'reason' => $errorMessage,
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        return $endpoints;
    }
}
