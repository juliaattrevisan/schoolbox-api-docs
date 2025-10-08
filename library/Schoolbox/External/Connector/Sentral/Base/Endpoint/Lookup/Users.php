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
     * Cache of user profiles indexed by external_id
     * both students and staff will be stored here
     * students first, then staff, so staff can override students (if they have the same external_id)
     * @var array
     */
    private array $userLookup = [];


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
        $this->initStudentLookup();
        $this->initStaffLookup();
        return $this->userLookup;
    }


    /**
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return void
     */
    private function initStudentLookup(): void
    {
        $result = $this->baseProfileMapper->getDatabase()->executeQuery("
            SELECT external_id, firstname, lastname, metadata
            FROM {$this->userProfileTable} sup
            INNER JOIN {$this->studentProfileTable} sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ");


        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $this->userLookup[(string)$row['external_id']] = $row;
        }
    }


    /**
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return void
     */
    private function initStaffLookup(): void
    {
        $result = $this->baseProfileMapper->getDatabase()->executeQuery("
            SELECT external_id, firstname, lastname, metadata
            FROM {$this->userProfileTable} sup
            INNER JOIN {$this->staffProfileTable} sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ");

        while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
            $this->userLookup[(string)$row['external_id']] = $row;
        }
    }
}
