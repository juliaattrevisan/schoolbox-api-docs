<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;

/**
 * Class Specification
 *
 * This explores an xml output to check whether certain nodes exist
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class Specification implements ISpecification
{

    /**
     * @var IApi
     */
    private $api;


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
     * @throws RuntimeException
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */
            ['method' => 'getStudents', 'args' => ['currentstatus' => 'current']],


            /**
             * Parent Profile nodes
             */


            /**
             * Staff Profile nodes
             */


            /**
             * Timetable nodes
             */

        ];

        $endpoints = [];

        foreach ($paths as $path) {
            $result = (bool)$this->api->retrieveDocument($path['method'], $path['args']);

            if ($result) {
                $endpoints[] = [
                    'message' => $path['method'],
                    'status' => ISpecification::STATUS_OK
                ];
            } else {
                $endpoints[] = [
                    'message' => $path['method'],
                    'status' => ISpecification::STATUS_MISSING
                ];
            }
        }

        /* Get a list of all the timetables to process
         */
        $timetables = [];

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $endpoints[] = [
                'message' => 'No timetables detected.',
                'status' => ISpecification::STATUS_MISSING
            ];
        }

        foreach ($timetables as $timetable) {
            $endpoints[] = [
                'message' => '',
                'status' => ISpecification::STATUS_OK
            ];
        }

        return $endpoints;
    }
}
