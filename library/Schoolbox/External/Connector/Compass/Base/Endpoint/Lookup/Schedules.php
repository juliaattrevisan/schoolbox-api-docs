<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Lookup;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Get a set of all the Schedules for each Academic Group that's isRelevant (active)
 * Academic Groups + Schedules basically defines your timetable cycle; it's a set of
 * events that occur, grouped by Academic Group's academicGroupCode (a year, typically)
 */
class Schedules
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * API for communicating with Compass.
     */
    private IApi $api;


    /**
     * A cached result to save an api hit
     */
    private ?array $cache = null;


    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param IApi            $api
     */
    public function __construct(LoggerInterface $log, IApi $api)
    {
        $this->log = $log;
        $this->api = $api;
    }


    /**
     * Generate a key-value lookup of (Academic Group, Schedule)
     *
     * @return array
     */
    public function generate(): array
    {
        /* Only ever generate Schedules once
         */
        if (null !== $this->cache) {
            return $this->cache;
        }

        $this->log->debug('Fetching Academic Groups...');

        $academicGroups = (new AcademicGroups($this->log, $this->api))->generate();

        $stack = [];

        foreach ($academicGroups as $academicGroup) {
            $year = $academicGroup['academicGroupCode'];

            $this->log->debug(sprintf(
                'Fetching Schedules for Academic Group %s',
                $academicGroup['academicGroupCode']
            ));

            $schedules = $this->api->retrieveDocument('schedule/v1/GetSchedule', [
                'year' => $year
            ]);

            /* Consume all Schedules for the permitted Activity Types contained therein
             * Crack open they eggs!
             */
            foreach ($schedules as $schedule) {
                $this->log->info(sprintf(
                    'Processing Schedule %s with %s activities',
                    $schedule['name'],
                    \count($schedule['activities'])
                ));

                $stack[$year]['schedules'][] = $schedule;
            }
        }

        /* This definitely gets super expensive, so cache this thing
         */
        $this->cache = $stack;

        return $stack;
    }
}
