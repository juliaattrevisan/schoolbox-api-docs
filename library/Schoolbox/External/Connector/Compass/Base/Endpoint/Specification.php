<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
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
        $lBound = \Alaress_Date_Format::now()->getUsingFormat('c');
        $uBound = \Alaress_Date_Format::now()->addDays(1)->getUsingFormat('c');

        $paths = [

            /**
             * Student Profile nodes
             */
            [
                'endpoint' => 'person/v1/GetStudents',
                'params' => [
                    'lBound' => $lBound,
                    'uBound' => $uBound
                ]
            ],


            /**
             * Parent Profile nodes
             */
            [
                'endpoint' => 'person/v1/GetParents',
                'params' => [
                    'lBound' => $lBound,
                    'uBound' => $uBound
                ]
            ],

            /**
             * Staff Profile nodes
             */
            [
                'endpoint' => 'person/v1/GetStaff',
                'params' => [
                    'lBound' => $lBound,
                    'uBound' => $uBound
                ]
            ],


            /**
             * Timetable nodes
             */
            [
                'endpoint' => 'reference/v1/GetAcademicGroups',
                'params' => [],
            ],
            [
                'endpoint' => 'schedule/v1/GetStructures',
                'params' => [],
            ],
            [
                'endpoint' => 'schedule/v1/GetSchedule',
                'params' => [],
            ],
            [
                // Blanks are valid here, but I need to differentiate between a
                // invalid class code and permission denied message.
                // If this returns a 500 error, then this is considered okay
                'endpoint' => 'schedule/v1/GetStandardClassDetails',
                'params' => [
                    'activityCode'      => '',
                    'academicGroupCode' => '',
                ],
                'responseCode500Ok' => true,
            ],

        ];

        $endpoints = [];

        foreach ($paths as $path) {
            $errorMessage = '';
            $message = $path['endpoint'];

            try {
                $result = (bool)$this->api->retrieveDocument($path['endpoint'], $path['params']);
            } catch (\RuntimeException | \DomainException | \JsonException $e) {
                $responseCode500Ok = $path['responseCode500Ok'] ?? false;
                if ($responseCode500Ok && $e->getCode() === 500) {
                    $result = true;
                    $message .= ' (returned response code 500, but this is expected)';
                } else {
                    $result = false;
                    $errorMessage = $e->getMessage();
                }
            }

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
