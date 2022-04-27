<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup;

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

            /* Schedules come with an allowImport flag which I presume we obey
             * There isn't any definition for what that actually means, but I presume
             * it denotes whether this schedule can be imported into other software
             * such as us.
             */
            foreach ($schedules as $schedule) {
                if ($schedule['allowImport']) {
                    $this->log->info(sprintf(
                        'Schedule %s with %s activities allows import',
                        $schedule['name'],
                        \count($schedule['activities'])
                    ));

                    $stack[$year]['schedules'][] = $schedule;
                } else {
                    $this->log->debug(sprintf(
                        'Schedule %s does not allow import; skipping',
                        $schedule['name']
                    ));
                }
            }
        }

        /* This definitely gets super expensive, so cache this thing
         */
        $this->cache = $stack;

        return $stack;
    }
}
