<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint
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
        $paths = [

            /**
             * Student Profile nodes
             */
            [
                'name' => 'Student Profiles with includes',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    $nextUrl = sprintf(
                        'students?page=%s&per_page=%s&include=%s',
                        1,
                        1,
                        implode(',', [
                            'contact_details',
                            'house',
                            'house.employees',
                            'year',
                            'medical_conditions',
                            'education_details',
                            'extended_details',
                            'sen_needs',
                            'identifiers',
                            'contacts',
                            'contacts.contact_details'
                        ])
                    );

                    try {
                        $this->api->retrieve('GET', $nextUrl, []);
                    } catch (\RuntimeException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result,
                    ];
                }
            ],


            /**
             * Parent Profile nodes
             * The parents already come with the students as includes - there are no separate
             * endpoints that we need to examine
             */


            /**
             * Staff Profile nodes
             */
            [
                'name' => 'Staff Profiles with includes',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    $nextUrl = sprintf(
                        'employees?page=%s&per_page=%s&include=%s',
                        1,
                        1,
                        implode(',', [
                            'contact_details',
                            'employment_details',
                            'groups',
                            'roles',
                            'identifiers'
                        ])
                    );

                    try {
                        $this->api->retrieve('GET', $nextUrl, []);
                    } catch (\RuntimeException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result,
                    ];
                }
            ],


            /**
             * Timetable nodes
             */
            [
                'name' => 'Timetable Classes with includes',
                'func' => function () {
                    $errorMessage = '';
                    $result = true;

                    $nextUrl = sprintf(
                        'classes?' . implode('&', [
                            'page=%s',
                            'per_page=%s',
                            'has_students=%s',
                            'has_lessons=%s',
                            'include=%s'
                        ]),
                        1,
                        1,
                        true,
                        true,
                        implode(',', [
                            'subject',
                            'students',
                            'employees',
                            'lessons',
                            'lessons.room',
                            'lessons.period'
                        ])
                    );

                    try {
                        $this->api->retrieve('GET', $nextUrl, []);
                    } catch (\RuntimeException | \GuzzleHttp\Exception\GuzzleException $e) {
                        $errorMessage = $e->getMessage();
                        $result = false;
                    }

                    return [
                        'errorMessage' => $errorMessage,
                        'result' => $result,
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
