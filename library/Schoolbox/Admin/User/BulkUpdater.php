<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\User;

class BulkUpdater
{

    /**
     * @var array
     */
    private $userIds;

    /**
     * @var array
     */
    private $campusIds;

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
     * BulkUpdater constructor.
     *
     * @param array  $userIds
     * @param array  $campusIds
     * @param string $year
     * @param int    $eportfolioEnabled
     * @param int    $emailFromUsername
     * @param int    $externalMailClient
     * @param int    $webmail
     * @param int    $accountEnabled
     * @param int    $hideContacts
     * @param int    $hideTimetable
     * @param string $position
     * @param int    $displayGradeGraph
     */
    public function __construct(
        array $userIds,
        array $campusIds,
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

        /* Cast ints to each of these id sets
         */
        $this->userIds = array_map('intval', $userIds);
        $this->campusIds = array_map('intval', $campusIds);

        /* Remember these scalars as they are
         */
        $this->year = $year;
        $this->eportfolioEnabled = $eportfolioEnabled;
        $this->emailFromUsername = $emailFromUsername;
        $this->externalMailClient = $externalMailClient;
        $this->webmail = $webmail;
        $this->accountEnabled = $accountEnabled;
        $this->hideContacts = $hideContacts;
        $this->hideTimetable = $hideTimetable;
        $this->position = $position;
        $this->displayGradeGraph = $displayGradeGraph;
    }


    /**
     * Update users
     */
    public function update(): int
    {

        /* Prepared Statements ftw
         *   One big updater
         *   Campus Deleter
         *   Campus Adder
         */

        /* Gather a set of ids and cast them to ints
         * Implode them into a csv for a WHERE IN clause
         */

        /* Collate SET commands for each of the scalar params provided
         */

        /* Set up a secondary query stack for campus involvements
         * because we have to delete/insert those specifically for each user
         */

        /* Execute the primary query, which is UPDATE user SET ... WHERE id IN ()
         * That updates the scalars for all selected users in one mega query
         */

        /* Iteratively execute the secondary query which updates campus involvements
         * you might be able to reduce queries further by using delete with joins
         * prior to inserting the updated campuses; or doing a diff, but that costs
         * you a query just to start and even then you need to process all rows
         * (we won't be in mapper land with its hydration benefits)
         *
         * https://stackoverflow.com/questions/652770/delete-with-join-in-mysql)
         */

        die();
        return \count($this->userIds);
    }
}
