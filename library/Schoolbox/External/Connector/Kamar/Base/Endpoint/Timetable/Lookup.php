<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

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
     * @var TimetablePeriodMapper
     */
    private $timetablePeriodMapper;


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var array
     */
    private $users = [];


    /**
     * @var array
     */
    private $periods = [];


    /**
     * Lookup constructor.
     *
     * @param TimetablePeriodMapper  $timetablePeriodMapper
     * @param UserMapper             $userMapper
     */
    public function __construct(TimetablePeriodMapper $timetablePeriodMapper, UserMapper $userMapper)
    {
        $this->timetablePeriodMapper = $timetablePeriodMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Facade method
     *
     * @return void
     * @throws Exception
     */
    public function init(): void
    {
        $this->users = $this->initUserLookup();
        $this->periods = $this->initPeriodLookup();
    }


    /**
     * Get a list of (user_id, synergy_id) tuples
     *
     * @return array
     */
    private function initUserLookup(): array
    {
        $userStatement = $this->userMapper->getDatabase()->query("
            SELECT u.`id`, username
            FROM {$this->userMapper->getTable()} u
            INNER JOIN `groups` g ON u.`group_id` = g.id
            WHERE synergy_id IS NOT NULL
        ");

        $users = [];

        foreach ($userStatement as $row) {
            $users[$row['username']] = $row['id'];
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
                luCampus.ext_timetable_code,
                period.day, period.id, period.title
            FROM luCampus
            LEFT JOIN sis_timetable_campus campus ON luCampus.id = campus.campus_id
            LEFT JOIN sis_timetable_cycle cycle ON campus.cycle_id = cycle.id
            LEFT JOIN sis_timetable_period period ON cycle.id = period.cycle_id
        ');

        $periods = [];

        foreach ($periodStatement as $row) {
            $ext = $row['ext_timetable_code'];
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
