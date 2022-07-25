<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Profile;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception;

/**
 * Class Lookup
 *
 * Generate a list of known users and known periods for creating class involvement
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\TimetableCalendar
 */
class Lookup
{
    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $timetablePeriodMapper;


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
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return array
     */
    private function initStudentLookup(): array
    {
        $userStatement = $this->timetablePeriodMapper->getDatabase()->query("
            SELECT external_id, firstname, lastname
            FROM sis_user_profile sup
            INNER JOIN sis_user_profile_student sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ");

        $students = [];

        foreach ($userStatement as $row) {
            $students[$row['external_id']] = $row;
        }

        return $students;
    }


    /**
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return array
     */
    private function initStaffLookup(): array
    {
        $userStatement = $this->timetablePeriodMapper->getDatabase()->query(
            '
            SELECT external_id, firstname, lastname
            FROM sis_user_profile sup
            INNER JOIN sis_user_profile_staff sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL AND category = :category
        ',
            [':category' => Profile::TEACHER]
        );

        $staff = [];

        foreach ($userStatement as $row) {
            $staff[$row['external_id']] = $row;
        }

        return $staff;
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
     * Get a list of (external_id, code) tuples
     *
     * @return array
     */
    private function initClassInvolvementLookup(): array
    {
        $classInvolvementStatement = $this->timetablePeriodMapper->getDatabase()->query('
            SELECT
                scu.external_id,
                sc.name, sc.code
            FROM sis_class_user scu
            INNER JOIN sis_class sc on scu.class_id = sc.id
        ');

        $involvements = [];

        foreach ($classInvolvementStatement as $row) {
            $externalId = $row['external_id'];
            $code = $row['code'];
            $name = $row['name'];

            $involvements[$externalId][$code] = $name;
        }

        return $involvements;
    }


    /**
     * Reinitialise user lookup upon each use
     * This is an index of student users with external ids
     *
     * @return array
     */
    public function getStudents(): array
    {
        return $this->initStudentLookup();
    }


    /**
     * Reinitialise user lookup upon each use
     * This is an index of staff users with external ids
     *
     * @return array
     */
    public function getStaff(): array
    {
        return $this->initStaffLookup();
    }


    /**
     * Reinitialise user lookup upon each use
     *
     * @return array
     * @throws Exception
     */
    public function getPeriods(): array
    {
        return $this->initPeriodLookup();
    }


    /**
     * Reinitialise user lookup upon each use
     *
     * @return array
     */
    public function getInvolvements(): array
    {
        return $this->initClassInvolvementLookup();
    }
}
