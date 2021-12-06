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
     * @var IApi
     */
    private IApi $api;


    /**
     * Specification constructor.
     *
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
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
                'func' => function() {
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
                'func' => function() {
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
                'func' => function() {
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
                'func' => function() {
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
                'func' => function() {
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
                'func' => function() {
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
                'func' => function() {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $monday = date('Y-m-d', strtotime('monday this week'));
                        $friday = date('Y-m-d', strtotime('friday this week'));

                        $students = $this->api->retrieveDocument('enrolments/student?include=person,primaryEnrolment,contacts,specialNeedsPrograms&limit=1');

                        $student = $students['data'][0];

                        $this->api->retrieveDocument("timetables/student/{$student['id']}/timetable-lessons?fromDate={$monday}&toDate={$friday}");
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
                'func' => function() {
                    $errorMessage = '';
                    $result = true;

                    try {
                        $monday = date('Y-m-d', strtotime('monday this week'));
                        $friday = date('Y-m-d', strtotime('friday this week'));

                        $staffs = $this->api->retrieveDocument('enrolments/staff?include=person,qualifications,employments&limit=1');

                        $staff = $staffs['data'][0];

                        $this->api->retrieveDocument("timetables/staff/{$staff['id']}/timetable-lessons?fromDate={$monday}&toDate={$friday}");
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
