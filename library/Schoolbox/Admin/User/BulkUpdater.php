<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\User;

use Alaress\Database\Predicate\Builder;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use PDOException;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\UserAccess;
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
    private User $sessionUser;


    /**
     * @var UserBulkMapper
     */
    private UserBulkMapper $userBulkMapper;


    /**
     * @var array
     */
    private array $userIds;


    /**
     * @var array
     */
    private array $campusIds = [];


    /**
     * @var int
     */
    private int $role;


    /**
     * @var int
     */
    private int $year;


    /**
     * @var int
     */
    private int $house;


    /**
     * @var int
     */
    private int $eportfolioEnabled;


    /**
     * @var int
     */
    private int $emailFromUsername;


    /**
     * @var int
     */
    private int $externalMailClient;


    /**
     * @var int
     */
    private int $accountEnabled;


    /**
     * @var int
     */
    private int $hideContacts;


    /**
     * @var int
     */
    private int $hideTimetable;


    /**
     * @var string
     */
    private string $position;


    /**
     * @var int
     */
    private int $displayGradeGraph;

    private int $revokeToken;

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
     * @param int            $accountEnabled
     * @param int            $hideContacts
     * @param int            $hideTimetable
     * @param string         $position
     * @param int            $displayGradeGraph
     * @param int            $revokeToken
     * @param int            $clearGroups
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
        int $accountEnabled,
        int $hideContacts,
        int $hideTimetable,
        string $position,
        int $displayGradeGraph,
        int $revokeToken,
        private int $clearGroups
    ) {
        $this->sessionUser    = $sessionUser;
        $this->userBulkMapper = $userBulkMapper;

        /* Cast ints to each of these id sets
         */
        $this->userIds = array_map('intval', $userIds);

        /* Only update campus assignation if selected
         */
        if (count($campusIds) > 0) {
            $this->campusIds = array_map('intval', $campusIds);
            //check if campus still exists... yay multiplayer schoolbox
            $bindings           = Builder::getBindings($this->campusIds, 'c');
            $validCampusId =  $this->userBulkMapper->getDatabase()->query("SELECT count(*) as campusCount FROM luCampus WHERE id in ({$bindings->getPlaceholder()}) AND deleted_at is NULL", $bindings->getParams());
            $validCampusId->execute();
            $campusCount = (int)$validCampusId->fetch()['campusCount'];
            if (count($this->campusIds) !== $campusCount) {
                throw new SchoolboxException("Campus has been deleted or does not exist.");
            }
        }

        /* Remember these scalars as they are
         */
        $this->role               = $role;
        $this->year               = $year;
        $this->house              = $house;
        $this->eportfolioEnabled  = $eportfolioEnabled;
        $this->emailFromUsername  = $emailFromUsername;
        $this->externalMailClient = $externalMailClient;
        $this->accountEnabled     = $accountEnabled;
        $this->hideContacts       = $hideContacts;
        $this->hideTimetable      = $hideTimetable;
        $this->position           = trim($position);
        $this->displayGradeGraph  = $displayGradeGraph;
        $this->revokeToken        = $revokeToken;
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

        /* Prepare user lookup
         * We log messages to the screen, and we need a person's full name here
         */
        $lookup = $this->prepareLookup();

        /* Set up prepared statements
         */
        $statements = $this->prepareStatements();

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
            $statements['campus']['delete']['statement']->execute($statements['campus']['delete']['params']);
            $statements['campus']['insert']['statement']->execute();
            $updated = true;

            /* Log the users we brutally updated en-masse
             */
            foreach ($statements['campus']['insert']['params'] as $campusParam) {
                $messages[] = "{$this->sessionUser->getFullname()} ({$this->sessionUser->getId()}) modified campus relations for {$lookup[$campusParam['userId']]} ({$campusParam['userId']})";
            }
        }

        if (\count($statements['token']['update']['params'])) {
            $statements['token']['update']['statement']->execute($statements['token']['update']['params']);
            /* Log the users we brutally updated en-masse
             */
            foreach ($statements['token']['update']['params'] as $key => $userId) {
                if ($key === 'userId') {
                    // this is session user id
                    continue;
                }
                $messages[] = "{$this->sessionUser->getFullname()} ({$this->sessionUser->getId()}) revoked token for {$lookup[$userId]} ({$userId})";
            }
        }

        if (\count($statements['groups']['delete']['params'])) {
            $statements['groups']['delete']['statement']->execute($statements['groups']['delete']['params']);

            $updated = true;
            /* Log the users we brutally updated en-masse
             */
            foreach ($statements['groups']['delete']['params'] as $key => $userId) {
                if ($key === 'service') {
                    // this is session user id
                    continue;
                }
                $messages[] = "{$this->sessionUser->getFullname()} ({$this->sessionUser->getId()}) groups cleared for {$lookup[$userId]} ({$userId})";
            }
        }

        /* Log messages to this userBulkMapper log output thing
         *
         * Only care about unique messages because a user will have multiple messages associated
         * when they change multiple campuses
         */
        $messages = array_values(array_unique($messages));

        foreach ($messages as $message) {
            $this->userBulkMapper->log($message, true);
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
            $bindings           = Builder::getBindings($this->userIds, 'u');
            $updatedAtStatement = $this->userBulkMapper->getDatabase()->prepare(
                "
                UPDATE `user` SET updated_at = :currentTime
                WHERE id IN ({$bindings->getPlaceholder()})
            "
            );

            /* Update user.updated at to be whatever the time is, for all the userIds we were given
             */
            $updatedAtStatement->execute(
                array_merge(
                    ['currentTime' => \Alaress_Date_Format::now()->getDatabase()],
                    $bindings->getParams()
                )
            );

            return new Collection($this->userIds);
        }

        return new Collection();
    }


    /**
     * Get a lookup of (id, fullname) tuples
     *
     * @return array
     * @throws PDOException
     */
    private function prepareLookup(): array
    {
        $bindings  = Builder::getBindings($this->userIds, 'u');
        $statement = $this->userBulkMapper->getDatabase()->prepare(
            "
            SELECT id, firstname, lastname FROM `user`
            WHERE id IN ({$bindings->getPlaceholder()})"
        );

        $statement->execute($bindings->getParams());

        $lookup = [];

        foreach ($statement as $row) {
            $lookup[$row['id']] = "{$row['firstname']} {$row['lastname']}";
        }

        return $lookup;
    }


    /**
     * Set up prepared statements
     *
     * @return array
     */
    private function prepareStatements(): array
    {
        $bindings = Builder::getBindings($this->userIds, 'u');

        /* A set of queries and their args
         */
        $statements = [
            'campus' => [
                'delete' => [
                    'statement' => null,
                    'params'    => [],
                ],
                'insert' => [
                    'statement' => null,
                    'params'    => [],
                ],
            ],
            'user'   => [
                'update' => [
                    'statement' => null,
                    'params'    => [],
                ],
            ],
            'token'  => [
                'update' => [
                    'statement' => null,
                    'params'    => [],
                ],
            ],
            'groups'  => [
                'delete' => [
                    'statement' => null,
                    'params'    => [],
                ],
            ],
        ];

        /* Prepared Statements ftw
         *   Campus Deleter
         *   Campus Adder
         *   One big updater
         */
        if (\count($this->campusIds)) {
            $statements['campus']['delete']['statement'] = $this->userBulkMapper->getDatabase()->prepare(
                "DELETE FROM userCampus WHERE uid IN ({$bindings->getPlaceholder()})"
            );
            $statements['campus']['delete']['params']    = $bindings->getParams();

            /* Set up a secondary query stack for campus involvements
             * because we have to delete/insert those specifically for each user
             */
            $campusInsertPlaceholders = [];

            foreach ($this->campusIds as $campusId) {
                foreach ($this->userIds as $userId) {
                    $statements['campus']['insert']['params'][] = [
                        'userId'   => $userId,
                        'campusId' => $campusId,
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
        $userUpdateParams  = [];

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
            $updatePlaceholder[]          = 'position_title = :position';
            $userUpdateParams['position'] = $this->position;
        }

        if ($this->role !== self::UNCHANGED) {
            $updatePlaceholder[]      = 'group_id = :role';
            $userUpdateParams['role'] = $this->role;
        }

        if ($this->eportfolioEnabled !== self::UNCHANGED) {
            $updatePlaceholder[]                   = 'eportfolio_enabled = :eportfolioEnabled';
            $userUpdateParams['eportfolioEnabled'] = $this->eportfolioEnabled;
        }

        if ($this->emailFromUsername !== self::UNCHANGED) {
            $updatePlaceholder[]                   = 'local_email = :emailFromUsername';
            $userUpdateParams['emailFromUsername'] = $this->emailFromUsername;
        }

        if ($this->externalMailClient !== self::UNCHANGED) {
            $updatePlaceholder[]                    = 'mail_handler = :externalMailClient';
            $userUpdateParams['externalMailClient'] = $this->externalMailClient;
        }

        if ($this->accountEnabled !== self::UNCHANGED) {
            $updatePlaceholder[]                = 'enabled = :accountEnabled';
            $userUpdateParams['accountEnabled'] = $this->accountEnabled;
        }

        if ($this->hideContacts !== self::UNCHANGED) {
            $updatePlaceholder[]              = 'hide_contacts = :hideContacts';
            $userUpdateParams['hideContacts'] = $this->hideContacts;
        }

        if ($this->hideTimetable !== self::UNCHANGED) {
            $updatePlaceholder[]               = 'hide_timetable = :hideTimetable';
            $userUpdateParams['hideTimetable'] = $this->hideTimetable;
        }

        if ($this->displayGradeGraph !== self::UNCHANGED) {
            $updatePlaceholder[]                   = 'display_grade_graph = :displayGradeGraph';
            $userUpdateParams['displayGradeGraph'] = $this->displayGradeGraph;
        }

        /* Set updated_at field too, if required
         */
        if (\count($updatePlaceholder)) {
            $updatePlaceholder[]           = 'updated_at = :updatedAt';
            $userUpdateParams['updatedAt'] = \Alaress_Date_Format::now()->getDatabase();

            $userUpdateStatementPlaceholders = implode(",\n", $updatePlaceholder);
            $bindings                        = Builder::getBindings($this->userIds, 'u');
            $query                           = "
                UPDATE `user`
                SET {$userUpdateStatementPlaceholders}
                WHERE id IN ({$bindings->getPlaceholder()})
            ";

            $statements['user']['update'] = [
                'statement' => $this->userBulkMapper->getDatabase()->prepare($query),
                'params'    => array_merge($userUpdateParams, $bindings->getParams()),
            ];
        }

        if ($this->revokeToken === 1) {
            $toDelete        = $this->userBulkMapper->getDatabase()->query(
                "SELECT user_id FROM user_jwt_id WHERE  user_id IN ({$bindings->getPlaceholder()}) AND deleted_at IS NULL",
                $bindings->getParams()
            );
            $deletingUserIds = $toDelete->fetchAll(\PDO::FETCH_COLUMN);

            if ($deletingUserIds) {
                $revokeTokenBindings = Builder::getBindings($deletingUserIds, 'rvu');

                $statements['token']['update']['statement'] = $this->userBulkMapper->getDatabase()->prepare(
                    "UPDATE user_jwt_id SET deleted_at = now(), deleted_by = :userId
                     WHERE user_id IN ({$revokeTokenBindings->getPlaceholder()})
                       AND deleted_at IS NULL"
                );
                $statements['token']['update']['params']    = array_merge(['userId' => $this->sessionUser->getId()], $revokeTokenBindings->getParams());
            }
        }

        if ($this->clearGroups === 1) {
            $clearGroupsBindings = Builder::getBindings($this->userIds, 'cg');
            $statements['groups']['delete']['statement'] = $this->userBulkMapper->getDatabase()->prepare(
                "DELETE FROM userAccess WHERE service = :service AND uid IN ({$clearGroupsBindings->getPlaceholder()})"
            );
            $statements['groups']['delete']['params']    = array_merge(['service' => UserAccess::SERVICE_HOMEPAGE], $clearGroupsBindings->getParams());
        }

        return $statements;
    }
}
