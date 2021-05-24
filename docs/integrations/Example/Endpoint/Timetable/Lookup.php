<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint\Timetable;

use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Lookup
 *
 * Generate a list of known users and known periods for creating class involvement
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint\Timetable
 */
class Lookup
{

    /**
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * @var array
     */
    private $users;


    /**
     * @var array
     */
    private $periods;


    /**
     * Lookup constructor.
     *
     * @param TimetablePeriodMapper  $timetablePeriodMapper
     */
    public function __construct(TimetablePeriodMapper $timetablePeriodMapper)
    {
        $this->timetablePeriodMapper = $timetablePeriodMapper;
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
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return array
     */
    private function initUserLookup(): array
    {
        $userStatement = $this->timetablePeriodMapper->getDatabase()->query('
            SELECT external_id, firstname, lastname
            FROM sis_user_profile sup
            WHERE external_id IS NOT NULL
        ');

        $users = [];

        foreach ($userStatement as $row) {
            $users[$row['external_id']] = $row;
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
                cycle.cycle_identifier,
                period.day, period.id, period.title
            FROM sis_timetable_cycle cycle
            LEFT JOIN sis_timetable_period period ON cycle.id = period.cycle_id
        ');

        $periods = [];

        foreach ($periodStatement as $row) {
            $ext = $row['cycle_identifier'];
            $day = $row['day'];
            $periodName = $row['title'];

            /* Minimum period
             */
            $period = $this->timetablePeriodMapper->createModel()
                ->setId($row['id']);

            $periods['ext'][$ext]['day'][$day]['period'][$periodName] = $period;
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
