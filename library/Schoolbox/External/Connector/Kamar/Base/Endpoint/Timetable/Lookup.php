<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Lookup
 *
 * Generate a list of known users and known periods so that each xml fragment
 * can receive this same information in an iteration
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class Lookup
{
    /**
     * @var array|null
     */
    private ?array $users;


    /**
     * @var array|null
     */
    private ?array $periods;


    /**
     * Lookup constructor.
     *
     * @param TimetablePeriodMapper              $timetablePeriodMapper
     * @param \Schoolbox\External\Profile\Mapper $baseProfileMapper
     */
    public function __construct(
        private TimetablePeriodMapper $timetablePeriodMapper,
        private \Schoolbox\External\Profile\Mapper $baseProfileMapper
    ) {
        $this->users = null;
        $this->periods = null;
    }


    /**
     * Facade method
     *
     * If users and periods weren't previously fetched, get them
     * Otherwise return whatever was previously found
     *
     * @return void
     * @throws Exception
     */
    public function init(): void
    {
        if (null === $this->users) {
            $this->users = $this->initUserLookup();
        }

        if (null === $this->periods) {
            $this->periods = $this->initPeriodLookup();
        }
    }


    /**
     * Get a list of (user_id, synergy_id) tuples
     *
     * @return array
     */
    private function initUserLookup(): array
    {
        $userStatement = $this->baseProfileMapper->getDatabase()->query("
            (SELECT 
                external_id AS externalId,
                title, 
                firstname, 
                lastname
            FROM sis_user_profile_staff sups 
            INNER JOIN {$this->baseProfileMapper->getTable()} b ON sups.sis_user_profile_id = b.id
            WHERE external_id IS NOT NULL)
            
            UNION 
            
            (SELECT 
                external_id AS externalId,
                title, 
                firstname, 
                lastname
            FROM sis_user_profile_student sups 
            INNER JOIN {$this->baseProfileMapper->getTable()} b ON sups.sis_user_profile_id = b.id
            WHERE external_id IS NOT NULL)
        ");

        $users = [];

        foreach ($userStatement as $row) {
            $users[$row['externalId']] = $row;
        }

        return $users;
    }


    /**
     * Get a list of (day, period) tuples
     *
     * @return array
     * @throws Exception
     */
    private function initPeriodLookup(): array
    {
        $periodStatement = $this->timetablePeriodMapper->getDatabase()->query('
            SELECT
                cycle.id AS cycle_id,
                period.day, period.id, period.sequence
            FROM sis_timetable_cycle cycle
            LEFT JOIN sis_timetable_period period ON cycle.id = period.cycle_id
        ');

        $periods = [];

        foreach ($periodStatement as $row) {
            $cycleId = $row['cycle_id'];
            $day = $row['day'];
            $sequence = $row['sequence'];

            /* Minimum period
             */
            $period = $this->timetablePeriodMapper->createModel()
                ->setId($row['id']);

            $periods['cycleId'][$cycleId]['day'][$day]['period'][$sequence] = $period;
        }

        return $periods;
    }


    /**
     * @return array
     */
    public function getUsers(): array
    {
        return $this->users;
    }


    /**
     * @return array
     */
    public function getPeriods(): array
    {
        return $this->periods;
    }
}
