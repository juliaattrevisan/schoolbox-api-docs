<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;

/**
 * Import Clipboard teams into schoolbox
 *
 * Clipboard Category/Subcategory equates to schoolbox Divisions which are
 * created progressively
 */
class Team implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var bool
     */
    private bool $membership;


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * @var Codec\Division
     */
    private Codec\Division $divisionCodec;


    /**
     * @var \Schoolbox\Fixtures\Team\Player\Mapper
     */
    private \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @param LoggerInterface                        $log
     * @param bool                                   $perform
     * @param IApi                                   $api
     * @param bool                                   $membership
     * @param Registry                               $registry
     * @param \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper
     * @param \Schoolbox\UserMapper                  $userMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        bool $membership,
        Registry $registry,
        \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper,
        \Schoolbox\UserMapper $userMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->membership = $membership;
        $this->divisionCodec = new Codec\Division();
        $this->registry = $registry;
        $this->teamPlayerMapper = $teamPlayerMapper;
        $this->userMapper = $userMapper;
    }


    /**
     * Import Sports, Divisions, Teams, Team Members from clipboard,
     * creating them if they don't already exist
     *
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* I guess have an ultralong season to basically remove it from typical usage
         * This season basically encompasses everything so we only need to spawn it once
         */
        $season = $this->registry->recallSeason();

        /* Consume data from Clipboard endpoint - each team comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'teams';

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        /* If --membership was supplied, also get the students
         * And (offensively) pass that setting as a bucket boolean
         *
         * Disgusting
         */
        $includeStudents = 'false';
        $lookup = [];

        if ($this->membership) {
            $includeStudents = 'true';

            $lookup = (new Lookup\Person($this->userMapper))->generate();
        }

        $teams = $this->api->retrieve('GET', $endpoint, [
            'includePositions' => 'true',
            'includeStudents' => $includeStudents
        ]);

        foreach ($teams['data'] as $teamData) {
            /* Sport identifiers
             */
            $teamActivityId = (int)$teamData['activity']['id'];
            $teamActivity = $teamData['activity']['name'] ?? '';

            /* Division identifiers
             */
            $teamCategory = $teamData['category']['name'] ?? '';
            $teamSubcategory = $teamData['subcategory']['name'] ?? '';

            /* Team identifiers
             */
            $teamId = (int)$teamData['id'];
            $teamName = $teamData['name'];

            /* Try to manufacture a division title
             */
            $divisionName = $this->divisionCodec->encode($teamCategory, $teamSubcategory, $teamActivity);

            $this->log->debug(sprintf(
                'Encoded category/subcategory/activity (%s, %s, %s) into schoolbox division name: %s',
                $teamCategory,
                $teamSubcategory,
                $teamActivity,
                $divisionName
            ));

            /* Try to spawn a corresponding Sport instance
             */
            $sport = $this->registry->recallSport($teamActivityId, $teamActivity);

            /* Try to spawn a corresponding Division instance
             */
            $division = $this->registry->recallDivision($season, $teamActivityId, $sport, $divisionName);

            /* Try to spawn a corresponding Team instance
             * $teamId is used later to figure out who vs who, when importing Matchups
             * Teams are indexed by ids for this particular usage, unlike others
             */
            $team = $this->registry->recallTeam($teamActivityId, $division, $teamId, $teamName);

            $this->log->info(sprintf(
                'Sport: %s (id %s), Division: %s (id %s), Team: %s (id %s)',
                $sport->getName(),
                $sport->getId(),
                $division->getName(),
                $division->getId(),
                $team->getName(),
                $team->getId()
            ));

            /* Each team has a set of positions, but we attribute Positions to
             * the sport in schoolbox. Inspect each team and from its list of
             * Positions, accumulate them into the corresponding Sport
             */
            foreach ($teamData['positions'] as $positionData) {
                $position = $this->registry->recallPosition($teamActivityId, $sport, (int)$positionData['id'], $positionData['name']);
            }

            /* If the --membership flag was set, ram in students and staff relationships too
             */
            if ($this->membership) {
                $this->importMemberships($lookup, $team, $teamData);
            }
        }
    }


    /**
     * Import the students listed in $teamData into schoolbox Fixtures,
     * associating them with $team
     *
     * @param array                          $lookup
     * @param \Schoolbox\Fixtures\Team\Model $team
     * @param array                          $teamData
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importMemberships(array $lookup, \Schoolbox\Fixtures\Team\Model $team, array $teamData): void
    {
        $this->log->debug(sprintf(
            'Processing memberships for team %s',
            $team->getName()
        ));

        /* If anyone was here,
         * they won't be anymore
         */
        $this->teamPlayerMapper->deleteByColumn('team_id', $team->getId());

        /* Find out who this external_id is
         */
        $sequence = 0;

        foreach ($teamData['students'] as $student) {
            $externalId = $student['smsId'];
            $userId = $lookup[$externalId] ?? null;
            $sequence++;

            /* If there is no user id, we didn't find this person in
             * our local user table - we can't put them into Fixtures
             */
            if (null === $userId) {
                $this->log->debug(sprintf(
                    'Clipboard human %s %s (externalId %s) does not have a corresponding schoolbox user record; skipping',
                    $student['firstName'],
                    $student['lastName'],
                    $student['smsId']
                ));

                continue;
            }

            /* I don't need to have the full hydration of a user;
             * I just need to fulfil the teamPlayerMapper model interface
             * so I can save this relation. Instead of having a full hydration
             * stored in ram, I'll just ram in a stub with the identifier
             */
            $teamPlayer = $this->teamPlayerMapper->createModel()
                ->setUser($this->userMapper->createModel()->setId($userId))
                ->setTeam($team)
                ->setSequence($sequence);

            $this->teamPlayerMapper->save($teamPlayer);

            $this->log->info(sprintf(
                'Assigned Clipboard human %s %s (%s) to team %s, using schoolbox user %s',
                $student['firstName'],
                $student['lastName'],
                $student['smsId'],
                $team->getName(),
                $userId
            ));
        }
    }
}
