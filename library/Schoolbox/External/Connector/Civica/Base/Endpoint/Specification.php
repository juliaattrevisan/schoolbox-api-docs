<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint
 */
class Specification
{
    /**
     * @var Api
     */
    private Api $api;


    /**
     * Specification constructor.
     *
     * @param Api $api
     */
    public function __construct(Api $api)
    {
        $this->api = $api;
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     */
    public function check(): array
    {
        $paths = [

            /* Example struct
             */
//            [
//                'endpoint' => '',
//                'params' => [],
//            ],

            /**
             * Student Profile nodes
             */
            [
                'endpoint' => 'Students/Jurisdiction'
            ],


            /**
             * Parent Profile nodes
             */
            [
                'endpoint' => 'StudentCaregivers/Jurisdiction'
            ],


            /**
             * Staff Profile nodes
             */
            [
                'endpoint' => 'Staff/Jurisdiction'
            ],


            /**
             * Timetable nodes
             */
            [
                'endpoint' => 'TeachingGroups/Jurisdiction'
            ]

        ];

        $endpoints = [];

        foreach ($paths as $path) {
            /* A short description of what went wrong (usually an Exception message)
             * when you tried to access $path['endpoint']
             */
            $errorMessage = '';

            /* The endpoint you tried to access; indexed in the Specification report
             */
            $message = $path['endpoint'];

            /* Try to access the endpoint and capture the outcome
             */
            try {
                $result = (bool)$this->api->retrieveOne('GET', $message, ['pageSize' => 1]);
            } catch (\RuntimeException $e) {
                $errorMessage = $e->getMessage();
                $result = false;
            }

            /* If it succeeded, show OK
             * Otherwise classify as MISSING and display an accompanying error message
             */
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
