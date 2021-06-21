<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

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
     * @throws \RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */
            [
                'endpoint' =>'person/v1/GetStudents',
                'params' => [
                    'lBound' => \Alaress_Date_Format::now()->getUsingFormat('c'),
                    'uBound' => \Alaress_Date_Format::now()->addDays(1)->getUsingFormat('c')
                ]
            ]


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
            $errorMessage = '';
            $message = $path['endpoint'];

            try {
                $result = (bool)$this->api->retrieveDocument($path['endpoint'], $path['params']);
            } catch (\RuntimeException $e) {
                $errorMessage = $e->getMessage();
                $result = false;
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
