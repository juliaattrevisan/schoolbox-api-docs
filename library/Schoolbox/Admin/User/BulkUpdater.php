<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\User;

use Alaress\Database\Wrapper\Db2\PDOStatement;
use Schoolbox\Collection;
use Schoolbox\UserMapper;

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
     * @var UserMapper
     */
    private $userMapper;


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
     * @var string
     */
    private $year;


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
     * @var PDOStatement
     */
    private $campusDeleteStatement;


    /**
     * @var PDOStatement
     */
    private $campusInsertStatement;


    /**
     * @var array
     */
    private $campusParams = [];


    /**
     * @var PDOStatement
     */
    private $userUpdateStatement;


    /**
     * @var array
     */
    private $userUpdateParams = [];


    /**
     * BulkUpdater constructor.
     *
     * @param UserMapper $userMapper
     * @param array      $userIds
     * @param array      $campusIds
     * @param int        $role
     * @param string     $year
     * @param int        $eportfolioEnabled
     * @param int        $emailFromUsername
     * @param int        $externalMailClient
     * @param int        $webmail
     * @param int        $accountEnabled
     * @param int        $hideContacts
     * @param int        $hideTimetable
     * @param string     $position
     * @param int        $displayGradeGraph
     */
    public function __construct(
        UserMapper $userMapper,
        array $userIds,
        array $campusIds,
        int $role,
        string $year,
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
        $this->userMapper = $userMapper;

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
        $this->year = trim($year);
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

        /* Set up prepared statements
         */
        $this->prepareStatements();

        /* Execute the primary query, which is UPDATE user SET ... WHERE id IN ()
         * That updates the scalars for all selected users in one mega query
         */
        if (\count($this->userUpdateParams)) {
            $this->userUpdateStatement->execute($this->userUpdateParams);

            $updated = true;
        }

        /* Iteratively execute the secondary query which updates campus involvements
         * you might be able to reduce queries further by using delete with joins
         * prior to inserting the updated campuses; or doing a diff, but that costs
         * you a query just to start and even then you need to process all rows
         * (we won't be in mapper land with its hydration benefits)
         *
         * https://stackoverflow.com/questions/652770/delete-with-join-in-mysql)
         */
        if (\count($this->campusParams)) {
            $this->campusDeleteStatement->execute();

            foreach ($this->campusParams as $campusParam) {
                $this->campusInsertStatement->execute($campusParam);

                /* TODO Should updating a user's campus also cause the user.updated_at field to change?
                 *  Not sure what users are expecting/how they're interpreting that usage
                 *  It costs another set of db queries anyway, so I left it out for now
                 */
            }

            $updated = true;
        }

        /* If user data was changed (either scalars or campus assignations)
         * then report back the affected user tally; otherwise, nothing changed
         * despite being provided vars
         */
        if ($updated) {
            return new Collection($this->userIds);
        }

        return new Collection();
    }


    /**
     * Set up prepared statements
     */
    private function prepareStatements(): void
    {
        /* Gather a set of ids and cast them to ints
         * Implode them into a csv for a WHERE IN clause
         */
        $userIds = implode(',', $this->userIds);

        /* Prepared Statements ftw
         *   Campus Deleter
         *   Campus Adder
         *   One big updater
         */
        if (\count($this->campusIds)) {
            $this->campusDeleteStatement = $this->userMapper->getDatabase()->prepare(
                "DELETE FROM userCampus WHERE uid IN ({$userIds})"
            );

            $this->campusInsertStatement = $this->userMapper->getDatabase()->prepare(
                'INSERT INTO userCampus (uid, campus) VALUES (:userId, :campusId)'
            );

            /* Set up a secondary query stack for campus involvements
             * because we have to delete/insert those specifically for each user
             */
            foreach ($this->campusIds as $campusId) {
                foreach ($this->userIds as $userId) {
                    $this->campusParams[] = [
                        'userId'   => $userId,
                        'campusId' => $campusId
                    ];
                }
            }
        }

        /* Collate SET commands for each of the scalar params provided
         */
        $updatePlaceholder = [];

        if ('' !== $this->year) {
            $updatePlaceholder[] = 'year = :year';
            $this->userUpdateParams['year'] = $this->year;
        }

        if ('' !== $this->position) {
            $updatePlaceholder[] = 'position_title = :position';
            $this->userUpdateParams['position'] = $this->position;
        }

        if ($this->role !== self::UNCHANGED) {
            $updatePlaceholder[] = 'group_id = :role';
            $this->userUpdateParams['role'] = $this->role;
        }

        if ($this->eportfolioEnabled !== self::UNCHANGED) {
            $updatePlaceholder[] = 'eportfolio_enabled = :eportfolioEnabled';
            $this->userUpdateParams['eportfolioEnabled'] = $this->eportfolioEnabled;
        }

        if ($this->emailFromUsername !== self::UNCHANGED) {
            $updatePlaceholder[] = 'local_email = :emailFromUsername';
            $this->userUpdateParams['emailFromUsername'] = $this->emailFromUsername;
        }

        if ($this->externalMailClient !== self::UNCHANGED) {
            $updatePlaceholder[] = 'mail_handler = :externalMailClient';
            $this->userUpdateParams['externalMailClient'] = $this->externalMailClient;
        }

        if ($this->webmail !== self::UNCHANGED) {
            $updatePlaceholder[] = 'webmail = :webmail';
            $this->userUpdateParams['webmail'] = $this->webmail;
        }

        if ($this->accountEnabled !== self::UNCHANGED) {
            $updatePlaceholder[] = 'enabled = :accountEnabled';
            $this->userUpdateParams['accountEnabled'] = $this->accountEnabled;
        }

        if ($this->hideContacts !== self::UNCHANGED) {
            $updatePlaceholder[] = 'hide_contacts = :hideContacts';
            $this->userUpdateParams['hideContacts'] = $this->hideContacts;
        }

        if ($this->hideTimetable !== self::UNCHANGED) {
            $updatePlaceholder[] = 'hide_timetable = :hideTimetable';
            $this->userUpdateParams['hideTimetable'] = $this->hideTimetable;
        }

        if ($this->displayGradeGraph !== self::UNCHANGED) {
            $updatePlaceholder[] = 'display_grade_graph = :displayGradeGraph';
            $this->userUpdateParams['displayGradeGraph'] = $this->displayGradeGraph;
        }

        /* Set updated_at field too, if required
         */
        if (\count($updatePlaceholder)) {
            $updatePlaceholder[] = 'updated_at = :updatedAt';
            $this->userUpdateParams['updatedAt'] = \Alaress_Date_Format::now()->getDatabase();

            $userUpdateStatementPlaceholders = implode(",\n", $updatePlaceholder);

            $query = "
                UPDATE `user`
                SET {$userUpdateStatementPlaceholders}
                WHERE id IN ({$userIds})
            ";

            $this->userUpdateStatement = $this->userMapper->getDatabase()->prepare($query);
        }
    }
}
