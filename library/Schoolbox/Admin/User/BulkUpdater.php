<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\User;

use PDOException;
use Schoolbox\Collection;
use Schoolbox\User;
use Schoolbox\UserBulkMapper;

/**
 * Class BulkUpdater
 *
 * Mass-update effect applied to all $userIds supplied when there is at least one
 * scalar param that changes, or at least one campus change
 *
 * When you supply a list of users, but leave all their stuff Unchanged (or empty string
 * which equates to the same effect) then it won't change anything.
 *
 * @package Schoolbox\Admin\User
 */
class BulkUpdater
{

    /**
     * hardcoded value that causes you to leave this value unchanged
     */
    private const UNCHANGED = -1;


    /**
     * The currently logged-in user
     *
     * @var User
     */
    private $sessionUser;


    /**
     * @var UserBulkMapper
     */
    private $userBulkMapper;


    /**
     * @var array
     */
    private $userIds;


    /**
     * @var array
     */
    private $campusIds = [];


    /**
     * @var int
     */
    private $role;


    /**
     * @var int
     */
    private $year;


    /**
     * @var int
     */
    private $house;


    /**
     * @var int
     */
    private $eportfolioEnabled;


    /**
     * @var int
     */
    private $emailFromUsername;


    /**
     * @var int
     */
    private $externalMailClient;


    /**
     * @var int
     */
    private $webmail;


    /**
     * @var int
     */
    private $accountEnabled;


    /**
     * @var int
     */
    private $hideContacts;


    /**
     * @var int
     */
    private $hideTimetable;


    /**
     * @var string
     */
    private $position;


    /**
     * @var int
     */
    private $displayGradeGraph;


    /**
     * BulkUpdater constructor.
     *
     * @param User           $sessionUser
     * @param UserBulkMapper $userBulkMapper
     * @param array          $userIds
     * @param array          $campusIds
     * @param int            $role
     * @param int            $year
     * @param int            $house
     * @param int            $eportfolioEnabled
     * @param int            $emailFromUsername
     * @param int            $externalMailClient
     * @param int            $webmail
     * @param int            $accountEnabled
     * @param int            $hideContacts
     * @param int            $hideTimetable
     * @param string         $position
     * @param int            $displayGradeGraph
     */
    public function __construct(
        User $sessionUser,
        UserBulkMapper $userBulkMapper,
        array $userIds,
        array $campusIds,
        int $role,
        int $year,
        int $house,
        int $eportfolioEnabled,
        int $emailFromUsername,
        int $externalMailClient,
        int $webmail,
        int $accountEnabled,
        int $hideContacts,
        int $hideTimetable,
        string $position,
        int $displayGradeGraph
    ) {
        $this->sessionUser = $sessionUser;
        $this->userBulkMapper = $userBulkMapper;

        /* Cast ints to each of these id sets
         */
        $this->userIds = array_map('intval', $userIds);

        /* Only update campus assignation if Unchanged was never selected
         */
        if (!in_array(self::UNCHANGED, $campusIds, false)) {
            $this->campusIds = array_map('intval', $campusIds);
        }

        /* Remember these scalars as they are
         */
        $this->role = $role;
        $this->year = $year;
        $this->house = $house;
        $this->eportfolioEnabled = $eportfolioEnabled;
        $this->emailFromUsername = $emailFromUsername;
        $this->externalMailClient = $externalMailClient;
        $this->webmail = $webmail;
        $this->accountEnabled = $accountEnabled;
        $this->hideContacts = $hideContacts;
        $this->hideTimetable = $hideTimetable;
        $this->position = trim($position);
        $this->displayGradeGraph = $displayGradeGraph;
    }


    /**
     * Update users
     */
    public function update(): Collection
    {

        /* No users to change? Nothing to do
         */
        if (!\count($this->userIds)) {
            return new Collection();
        }

        /* There's an edge case where you selected users,
         * but you left all their data set to Unchanged
         */
        $updated = false;

        /* A list of log messages
         */
        $messages = [];

        /* Gather a set of ids and cast them to ints
         * Implode them into a csv for a WHERE IN clause
         */
        $userIds = implode(',', $this->userIds);

        /* Prepare user lookup
         * We log messages to the screen, and we need a person's full name here
         */
        $lookup = $this->prepareLookup($userIds);

        /* Set up prepared statements
         */
        $statements = $this->prepareStatements($userIds);

        /* Execute the primary query, which is UPDATE user SET ... WHERE id IN ()
         * That updates the scalars for all selected users in one mega query
         */
        if (\count($statements['user']['update']['params'])) {
            $statements['user']['update']['statement']->execute($statements['user']['update']['params']);
            $updated = true;

            /* Log the users we brutally updated en masse
             */
            foreach ($this->userIds as $userId) {
                $messages[] = "{$this->sessionUser->getFullname()} ({$this->sessionUser->getId()}) modified user details for {$lookup[$userId]} ({$userId})";
            }
        }

        /* Iteratively execute the secondary query which updates campus involvements
         * you might be able to reduce queries further by using delete with joins
         * prior to inserting the updated campuses; or doing a diff, but that costs
         * you a query just to start and even then you need to process all rows
         * (we won't be in mapper land with its hydration benefits)
         *
         * https://stackoverflow.com/questions/652770/delete-with-join-in-mysql)
         */
        if (\count($statements['campus']['insert']['params'])) {
            /* Updating a user's campus
             */
            $statements['campus']['delete']['statement']->execute();
            $statements['campus']['insert']['statement']->execute();
            $updated = true;

            /* Log the users we brutally updated en-masse
             */
            foreach ($statements['campus']['insert']['params'] as $campusParam) {
                $messages[] = "{$this->sessionUser->getFullname()} ({$this->sessionUser->getId()}) modified campus relations for {$lookup[$campusParam['userId']]} ({$campusParam['userId']})";
            }
        }

        /* Log messages to this userBulkMapper log output thing
         *
         * Only care about unique messages because a user will have multiple messages associated
         * when they change multiple campuses
         */
        $messages = array_values(array_unique($messages));

        foreach ($messages as $message) {
            $this->userBulkMapper->log($message);
        }

        /* If user data was changed (either scalars or campus assignations)
         * then report back the affected user tally; otherwise, nothing changed
         * despite being provided vars
         */
        if ($updated) {
            /* Should updating a user's campus also cause the user.updated_at field to change?
             *
             * There were three options:
             *  1. Update every row specially, for highest accuracy down to the second
             *     That costs n(userIds) individual queries, which is horribly expensive but most accurate
             *  2. Change updated_at to be the time when the updater successfully finished running
             *     This costs one, maybe two, query; but it means everyone will look like they got
             *     updated at exactly the same time (not as accurate, but probably best bang for buck)
             *  3. updated_at remains unchanged (lowest accuracy)
             *
             * We choze (2)
             */
            $updatedAtStatement = $this->userBulkMapper->getDatabase()->prepare("
                UPDATE `user` SET updated_at = :currentTime
                WHERE id IN ({$userIds})
            ");

            /* Update user.updated at to be whatever the time is, for all the userIds we were given
             */
            $updatedAtStatement->execute([
                'currentTime' => \Alaress_Date_Format::now()->getDatabase()
            ]);

            return new Collection($this->userIds);
        }

        return new Collection();
    }


    /**
     * Get a lookup of (id, fullname) tuples
     *
     * @param string $userIds
     * @return array
     * @throws PDOException
     */
    private function prepareLookup(string $userIds): array
    {
        $statement = $this->userBulkMapper->getDatabase()->prepare("
            SELECT id, firstname, lastname FROM `user`
            WHERE id IN ({$userIds})");

        $statement->execute();

        $lookup = [];

        foreach ($statement as $row) {
            $lookup[$row['id']] = "{$row['firstname']} {$row['lastname']}";
        }

        return $lookup;
    }


    /**
     * Set up prepared statements
     *
     * @param string $userIds
     * @return array
     */
    private function prepareStatements(string $userIds): array
    {

        /* A set of queries and their args
         */
        $statements = [
            'campus' => [
                'delete' => [
                    'statement' => null
                ],
                'insert' => [
                    'statement' => null,
                    'params' => []
                ]
            ],
            'user' => [
                'update' => [
                    'statement' => null,
                    'params' => []
                ]
            ],
        ];

        /* Prepared Statements ftw
         *   Campus Deleter
         *   Campus Adder
         *   One big updater
         */
        if (\count($this->campusIds)) {
            $statements['campus']['delete']['statement'] = $this->userBulkMapper->getDatabase()->prepare(
                "DELETE FROM userCampus WHERE uid IN ({$userIds})"
            );

            /* Set up a secondary query stack for campus involvements
             * because we have to delete/insert those specifically for each user
             */
            $campusInsertPlaceholders = [];

            foreach ($this->campusIds as $campusId) {
                foreach ($this->userIds as $userId) {
                    $statements['campus']['insert']['params'][] = [
                        'userId'   => $userId,
                        'campusId' => $campusId
                    ];

                    $campusInsertPlaceholders[] = "({$userId}, {$campusId})";
                }
            }

            /* csv that placeholder set
             */
            $campusInsertPlaceholder = implode(",\n", $campusInsertPlaceholders);

            $statements['campus']['insert']['statement'] = $this->userBulkMapper->getDatabase()->prepare(
                "INSERT INTO userCampus (uid, campus) VALUES {$campusInsertPlaceholder}"
            );
        }

        /* Collate SET commands for each of the scalar params provided
         */
        $updatePlaceholder = [];
        $userUpdateParams = [];

        if ($this->year !== self::UNCHANGED) {
            $updatePlaceholder[] = 'year_id = :year';

            if (0 === $this->year) {
                $userUpdateParams['year'] = null;
            } else {
                $userUpdateParams['year'] = $this->year;
            }
        }

        if ($this->house !== self::UNCHANGED) {
            $updatePlaceholder[] = 'house = :house';

            if (0 === $this->house) {
                $userUpdateParams['house'] = null;
            } else {
                $userUpdateParams['house'] = $this->house;
            }
        }

        if ('' !== $this->position) {
            $updatePlaceholder[] = 'position_title = :position';
            $userUpdateParams['position'] = $this->position;
        }

        if ($this->role !== self::UNCHANGED) {
            $updatePlaceholder[] = 'group_id = :role';
            $userUpdateParams['role'] = $this->role;
        }

        if ($this->eportfolioEnabled !== self::UNCHANGED) {
            $updatePlaceholder[] = 'eportfolio_enabled = :eportfolioEnabled';
            $userUpdateParams['eportfolioEnabled'] = $this->eportfolioEnabled;
        }

        if ($this->emailFromUsername !== self::UNCHANGED) {
            $updatePlaceholder[] = 'local_email = :emailFromUsername';
            $userUpdateParams['emailFromUsername'] = $this->emailFromUsername;
        }

        if ($this->externalMailClient !== self::UNCHANGED) {
            $updatePlaceholder[] = 'mail_handler = :externalMailClient';
            $userUpdateParams['externalMailClient'] = $this->externalMailClient;
        }

        if ($this->webmail !== self::UNCHANGED) {
            $updatePlaceholder[] = 'webmail = :webmail';
            $userUpdateParams['webmail'] = $this->webmail;
        }

        if ($this->accountEnabled !== self::UNCHANGED) {
            $updatePlaceholder[] = 'enabled = :accountEnabled';
            $userUpdateParams['accountEnabled'] = $this->accountEnabled;
        }

        if ($this->hideContacts !== self::UNCHANGED) {
            $updatePlaceholder[] = 'hide_contacts = :hideContacts';
            $userUpdateParams['hideContacts'] = $this->hideContacts;
        }

        if ($this->hideTimetable !== self::UNCHANGED) {
            $updatePlaceholder[] = 'hide_timetable = :hideTimetable';
            $userUpdateParams['hideTimetable'] = $this->hideTimetable;
        }

        if ($this->displayGradeGraph !== self::UNCHANGED) {
            $updatePlaceholder[] = 'display_grade_graph = :displayGradeGraph';
            $userUpdateParams['displayGradeGraph'] = $this->displayGradeGraph;
        }

        /* Set updated_at field too, if required
         */
        if (\count($updatePlaceholder)) {
            $updatePlaceholder[] = 'updated_at = :updatedAt';
            $userUpdateParams['updatedAt'] = \Alaress_Date_Format::now()->getDatabase();

            $userUpdateStatementPlaceholders = implode(",\n", $updatePlaceholder);

            $query = "
                UPDATE `user`
                SET {$userUpdateStatementPlaceholders}
                WHERE id IN ({$userIds})
            ";

            $statements['user']['update'] = [
                'statement' => $this->userBulkMapper->getDatabase()->prepare($query),
                'params' => $userUpdateParams,
            ];
        }

        return $statements;
    }
}
