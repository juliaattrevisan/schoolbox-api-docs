<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use RuntimeException;
use Schoolbox\External\Importer\Endpoint\Specification\ISpecification;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Exception\InvalidUriPartException;

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
     * Specification constructor.
     */
    public function __construct()
    {
    }


    /**
     * Get a list of nodes that did, or didn't, get configured properly
     *
     * @return array
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws InvalidUriPartException
     */
    public function check(): array
    {
        $paths = [

            /**
             * Student Profile nodes
             */


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

        /* Find the timetable identifier, for reference, in the status screen
         */

        /* Get a list of all the timetables to process
         */
        $timetables = [];

        /* If there aren't any timetables, log it and exit
         */
        if (!\count($timetables)) {
            $endpoints[] = [
                'message' => 'No PublishedTimetables detected.',
                'status' => ISpecification::STATUS_MISSING
            ];
        }

        foreach ($timetables as $timetable) {
            $timetableMessage = sprintf(
                'Found timetable %s: select %s for campus cycle_identifier',
                trim((string)$timetable->Name),
                trim((string)$timetable->xpath('@Id')[0]->Id)
            );

            $endpoints[] = [
                'message' => $timetableMessage,
                'status' => ISpecification::STATUS_OK
            ];
        }

        return $endpoints;
    }
}
