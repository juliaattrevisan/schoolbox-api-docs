<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Lookup;

/**
 * Class Lookup
 *
 * Generate a list of known users and known periods for creating class involvement
 */
class Users
{
    /**
     * Table names as derived from their corresponding mappers
     *
     * @var string
     */
    private string $userProfileTable;
    private string $studentProfileTable;
    private string $staffProfileTable;


    /**
     * Lookup constructor.
     */
    public function __construct(
        private \Schoolbox\External\Profile\Mapper $baseProfileMapper,
        private \Schoolbox\External\Profile\Student\Mapper $studentProfileMapper,
        private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
        $this->userProfileTable = $this->baseProfileMapper->getTable();
        $this->studentProfileTable = $this->studentProfileMapper->getTable();
        $this->staffProfileTable = $this->staffProfileMapper->getTable();
    }


    /**
     * Reinitialise user lookup upon each use
     * This is an index of student users with external ids
     *
     * @return array
     */
    public function generate(): array
    {
        /* We have to presume that external ids are unique, for now
         * I know in some circles they aren't, but here, they *seem* to be
         */
        return array_merge($this->initStudentLookup(), $this->initStaffLookup());
    }


    /**
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return array
     */
    private function initStudentLookup(): array
    {
        $userStatement = $this->baseProfileMapper->getDatabase()->query("
            SELECT external_id, firstname, lastname, metadata
            FROM {$this->userProfileTable} sup
            INNER JOIN {$this->studentProfileTable} sups ON sup.id = sups.sis_user_profile_id
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
        $userStatement = $this->baseProfileMapper->getDatabase()->query("
            SELECT external_id, firstname, lastname, metadata
            FROM {$this->userProfileTable} sup
            INNER JOIN {$this->staffProfileTable} sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ");

        $staff = [];

        foreach ($userStatement as $row) {
            $staff[(string)$row['external_id']] = $row;
        }

        return $staff;
    }
}
