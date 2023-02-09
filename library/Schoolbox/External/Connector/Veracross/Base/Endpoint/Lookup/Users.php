<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Event\Mapper;

/**
 * Class Lookup
 *
 * Generate a list of known users and known periods for creating class involvement
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint\TimetableCalendar
 */
class Users
{
    /**
     * Lookup constructor.
     */
    public function __construct(private Mapper $eventMapper)
    {
    }


    /**
     * Reinitialise user lookup upon each use
     * This is an index of student users with external ids
     *
     * @return array
     */
    public function generate(): array
    {
        /* Combine these two; external ids are Veracross' internal identifier
         * and should be unique across the board
         *
         * return array_merge($this->initStudentLookup(), $this->initStaffLookup());
         */
        return $this->initStudentLookup();
    }


    /**
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return array
     */
    private function initStudentLookup(): array
    {
        $userStatement = $this->eventMapper->getDatabase()->query("
            SELECT external_id, firstname, lastname
            FROM sis_user_profile sup
            INNER JOIN sis_user_profile_student sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ");

        $students = [];

        foreach ($userStatement as $row) {
            $students[(string)$row['external_id']] = $row;
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
        $userStatement = $this->eventMapper->getDatabase()->query('
            SELECT external_id, firstname, lastname
            FROM sis_user_profile sup
            INNER JOIN sis_user_profile_staff sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ');

        $staff = [];

        foreach ($userStatement as $row) {
            $staff[(string)$row['external_id']] = $row;
        }

        return $staff;
    }
}
