<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\User;

use Alaress\Database\Wrapper\Db2\PDOStatement;
use Schoolbox\UserMapper;

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
    private $campusIds;


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
    )
    {
        $this->userMapper = $userMapper;

        /* Cast ints to each of these id sets
         */
        $this->userIds = array_map('intval', $userIds);
        $this->campusIds = array_map('intval', $campusIds);

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
    public function update(): int
    {

        /* Set up prepared statements
         */
        $this->prepareStatements();

        /* Execute the primary query, which is UPDATE user SET ... WHERE id IN ()
         * That updates the scalars for all selected users in one mega query
         */
        $this->userUpdateStatement->execute($this->userUpdateParams);

        /* Iteratively execute the secondary query which updates campus involvements
         * you might be able to reduce queries further by using delete with joins
         * prior to inserting the updated campuses; or doing a diff, but that costs
         * you a query just to start and even then you need to process all rows
         * (we won't be in mapper land with its hydration benefits)
         *
         * https://stackoverflow.com/questions/652770/delete-with-join-in-mysql)
         */
        $this->campusDeleteStatement->execute();

        foreach ($this->campusParams as $campusParam) {
            $this->campusInsertStatement->execute($campusParam);
        }

        return \count($this->userIds);
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
        $this->campusDeleteStatement = $this->userMapper->getDatabase()->prepare(
            "DELETE FROM userCampus WHERE uid IN {$userIds}"
        );

        $this->campusInsertStatement = $this->userMapper->getDatabase()->prepare(
            'INSERT INTO userCampus (uid, campus) VALUES (:userId, :campusId)'
        );

        /* Set up a secondary query stack for campus involvements
         * because we have to delete/insert those specifically for each user
         */
        if (!in_array(self::UNCHANGED, $this->campusIds, false)) {
            foreach ($this->userIds as $userId) {
                foreach ($this->campusIds as $campusId) {
                    $this->campusParams[] = [
                        'userId' => $userId,
                        'campsuId' => $campusId
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

        $userUpdateStatementPlaceholders = implode(",\n", $updatePlaceholder);

        $query = "
            UPDATE `user`
            SET {$userUpdateStatementPlaceholders}
            WHERE id IN {$userIds}
        ";

        $this->userUpdateStatement = $this->userMapper->getDatabase()->prepare($query);
    }
}
