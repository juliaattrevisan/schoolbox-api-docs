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
 * Data generator for academic groups from Compass.
 *
 * Academic Groups are clostest to a Timetable Cycle. At wyndham we saw usages like
 * "2018 Academic", "2017 Academic" - only one of which could be isRelevant (that is,
 * active) at a given time
 */
class AcademicGroups
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
     * Generate and return academic groups.
     *
     * @return array
     */
    public function generate(): array
    {
        /* Only ever generate AcademicGroups once
         */
        if (null !== $this->cache) {
            return $this->cache;
        }

        $stack = [];

        $academicGroups = $this->api->retrieveDocument('reference/v1/GetAcademicGroups', []);

        /* isRelevant denotes the currently active Academic Group
         * There can be only one and we don't care about the others (conceptually)
         * though maybe compass is buggy and lets multiple isRelevant AcademicGroups exist
         * In any case, stack them all up
         */
        foreach ($academicGroups['d'] as $academicGroup) {
            if (!$academicGroup['isRelevant']) {
                $this->log->debug(sprintf(
                    'Academic Group %s isRelevant is false; skipping',
                    $academicGroup['name']
                ));

                continue;
            }

            $this->log->info(sprintf(
                'Academic Group %s found',
                $academicGroup['name']
            ));

            $stack[] = $academicGroup;
        }

        $this->cache = $stack;

        return $stack;
    }
}
