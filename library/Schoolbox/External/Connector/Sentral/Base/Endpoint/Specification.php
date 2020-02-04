<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use UnexpectedValueException;

/**
 * Class Specification
 *
 * This explores an endpoint output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Specification implements ISpecification
{

    /**
     * @var Api
     */
    private $api;


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
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */
            'enrolments/student-contact?include=person,student,studentPerson&limit=1',
            'enrolments/emergency-contact-link?limit=1',
            'enrolments/enrolment?limit=1',
            'plans/student-plan?limit=1',
            'plans/student-plan-type?limit=1',
            'enrolments/medical-condition?limit=1',
            'enrolments/person-phone?limit=1',

            /**
             * Parent Profile nodes
             */


            /**
             * Staff Profile nodes
             */
            'enrolments/staff?include=person,qualifications&limit=1',


            /**
             * Timetable nodes
             */

        ];

        $endpoints = [];

        foreach ($paths as $path) {
            try {
                $result = $this->api->retrieveDocument($path);

                if (\count($result['data'])) {
                    $endpoints[] = [
                        'message' => $path,
                        'status' => ISpecification::STATUS_OK
                    ];
                } else {
                    $endpoints[] = [
                        'message' => $path,
                        'status' => ISpecification::STATUS_EMPTY
                    ];
                }
            } catch (UnexpectedValueException $e) {
                $endpoints[] = [
                    'message' => $path,
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        return $endpoints;
    }
}
