<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

use Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile\Profile;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;


/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
class Specification
{
    /**
     * A list of endpoints and resultant payloads that we obtained en route
     * because every request costs ~1 second from the US to here
     *
     * These are (endpoint name, payload array) tuples
     *
     * @var Registry
     */
    private Registry $registry;


    /**
     * Specification constructor.
     *
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
        $this->registry = new Registry();
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
                'name' => 'Student Profiles: List (student)',
                'func' => function() {
                    return $this->trial('students');
                }
            ],
            [
                'name' => 'Student Profiles: Medical Lookup (health/patients)',
                'func' => function() {
                    return $this->trial('health/patients');
                }
            ],
            [
                'name' => 'Student Profiles: Languages Lookup (admission/languages)',
                'func' => function() {
                    return $this->trial('admission/languages');
                }
            ],

            [
                'name' => 'Student Profiles: Emergency Contacts Lookup (emergency_contacts)',
                'func' => function() {
                    return $this->trial('emergency_contacts');
                }
            ],


            /**
             * Parent Profile nodes
             */
            [
                'name' => 'Parent Profiles: Household Lookup (parents)',
                'func' => function() {
                    return $this->trial('parents');
                }
            ],
            [
                'name' => 'Parent Profiles: Relationship Lookup (relationships)',
                'func' => function() {
                    return $this->trial('relationships');
                }
            ],
            [
                'name' => 'Parent/Staff Profiles: Contact Info Lookup (contact_info)',
                'func' => function() {
                    return $this->trial('contact_info');
                }
            ],


            /**
             * Staff Profile nodes
             */
            [
                'name' => 'Staff Profiles: List (staff_faculty)',
                'func' => function() {
                    return $this->trial('staff_faculty');
                }
            ],
//
//
//            /**
//             * Timetable nodes
//             */
//            [
//                'name' => 'Timetable Classes',
//            ],
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


    /**
     * Try accessing $endpoint, caching its output if it succeeds
     *
     * @param string $endpoint
     * @return array
     */
    private function trial(string $endpoint): array
    {
        $errorMessage = '';
        $result = true;

        /* Assumption:
         * Only need the first record on first page
         */
        try {
            $payload = $this->api->retrieveOne('GET', $endpoint, Profile::SCOPE, 1, 1);

            /* Remember this for later
             */
            $this->registry->remember($endpoint, $payload['data']);
        } catch (\DomainException | \JsonException | \RuntimeException $e) {
            $errorMessage = $e->getMessage();
            $result = false;
        }

        return [
            'errorMessage' => $errorMessage,
            'result' => $result,
        ];
    }
}
