<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Watchlist;
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
        private Watchlist $watcher,
        private array $watchlist,
        private Mapper $eventMapper
    ) {
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
         * // https://www.php.net/manual/en/function.array-merge.php
         * // Don't forget that numeric keys will be renumbered!
         * return array_merge($this->initStudentLookup(), $this->initStaffLookup());
         *
         * new solution is to use a single array that we keep building up [SA-20288]
         */
        $this->initStudentLookup();
//        $this->initStaffLookup();
        return $this->userLookup;
    }


    /**
     * Get a list of (external_id, sis_user_profile) tuples
     *
     * @return void
     */
    private function initStudentLookup(): void
    {
        $userProfileTable    = \Schoolbox\External\Profile\Mapper::getInstance()->getTable();
        $studentProfileTable = \Schoolbox\External\Profile\Student\Mapper::getInstance()->getTable();

        $userStatement = $this->eventMapper->getDatabase()->query("
            SELECT external_id, firstname, lastname
            FROM {$userProfileTable} sup
            INNER JOIN {$studentProfileTable} sups ON sup.id = sups.sis_user_profile_id
            WHERE external_id IS NOT NULL
        ");

        foreach ($userStatement as $row) {
            $externalId = trim((string)$row['external_id']);

            /* Only import for specific users
             */
            if (!$this->watcher->isOnWatchlist($externalId, $this->watchlist)) {
                continue;
            }

            $this->userLookup[$externalId] = $row;
        }
    }


    /* Temporarily disabled
     * We don't know where staff timetable located right now
     */
//    /**
//     * Get a list of (external_id, sis_user_profile) tuples
//     *
//     * @return void
//     */
//    private function initStaffLookup(): void
//    {
//        $userStatement = $this->eventMapper->getDatabase()->query('
//            SELECT external_id, firstname, lastname
//            FROM sis_user_profile sup
//            INNER JOIN sis_user_profile_staff sups ON sup.id = sups.sis_user_profile_id
//            WHERE external_id IS NOT NULL
//        ');
//
//        foreach ($userStatement as $row) {
//            $this->userLookup[(string)$row['external_id']] = $row;
//        }
//    }
}
