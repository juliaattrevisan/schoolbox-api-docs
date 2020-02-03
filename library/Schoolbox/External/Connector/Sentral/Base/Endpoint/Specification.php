<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Psr\Log\LoggerInterface;
use UnexpectedValueException;

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
     * Status text for existing nodes
     */
    private const STATUS_OK = 'OK';


    /**
     * Status text for missing nodes
     */
    private const STATUS_MISSING = 'MISSING';


    /**
     * Status text for existing nodes that are empty but probably should contain something
     */
    private const STATUS_EMPTY = 'EMPTY';


    /**
     * Number of spaces for output formatting
     */
    private const PADDING = 10;


    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var Api
     */
    private $api;


    /**
     * Specification constructor.
     *
     * @param LoggerInterface|null $log
     * @param Api                  $api
     */
    public function __construct(?LoggerInterface $log, Api $api)
    {
        $this->log = $log;
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

        $paddedLength = max(array_map('\strlen', $paths)) + self::PADDING;

        $messages = [];

        foreach ($paths as $path) {
            $paddedPath = str_pad($path, $paddedLength);

            try {
                $result = $this->api->retrieveDocument($path);

                if (\count($result['data'])) {
                    $message = "{$paddedPath} " . self::STATUS_OK;

                    if ($this->log) {
                        $this->log->info($message);
                    }
                } else {
                    $message = "{$paddedPath} " . self::STATUS_EMPTY;

                    if ($this->log) {
                        $this->log->warning($message);
                    }
                }
            } catch (UnexpectedValueException $e) {
                $message = "{$paddedPath} " . self::STATUS_MISSING;

                if ($this->log) {
                    $this->log->warning($message);
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }
}
