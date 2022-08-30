<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Hierarchy\Lookup\Person;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;
use Schoolbox\Fixtures\Division\Model;
use Schoolbox\Fixtures\Division\User\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\LockInterface;

/**
 * Import Clipboard teams into schoolbox
 *
 * Clipboard Category/Subcategory equates to schoolbox Divisions which are
 * created progressively
 */
class Team implements IStrategy
{
    /**
     * Short-circuit to prevent infinite loops
     */
    private const MAX_CALLS = 100;


    /**
     * Number of results per page
     */
    private const NUM_PER_PAGE = 10;


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var LockInterface
     */
    private LockInterface $lock;


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
     * @var Person
     */
    private Lookup\Person $userLookup;


    /**
     * @var \Schoolbox\Fixtures\Division\User\Mapper
     */
    private \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Player\Mapper
     */
    private \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Position\Mapper
     */
    private \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper;


    /**
     * @var \Schoolbox\Fixtures\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Coach\Mapper $coachMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper;


    /**
     * @var \Schoolbox\UserMapper
     */
    private \Schoolbox\UserMapper $userMapper;


    /**
     * @var Codec\Division
     */
    private Codec\Division $divisionCodec;


    /**
     * @param LoggerInterface                          $log
     * @param bool                                     $perform
     * @param LockInterface                            $lock
     * @param IApi                                     $api
     * @param bool                                     $membership
     * @param Registry                                 $registry
     * @param Person                                   $userLookup
     * @param Mapper                                   $divisionUserMapper
     * @param \Schoolbox\Fixtures\Team\Player\Mapper   $teamPlayerMapper
     * @param \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper
     * @param \Schoolbox\Fixtures\Coach\Mapper         $coachMapper
     * @param \Schoolbox\Fixtures\Team\Coach\Mapper    $teamCoachMapper
     * @param UserMapper                               $userMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        LockInterface $lock,
        IApi $api,
        bool $membership,
        Registry $registry,
        Lookup\Person $userLookup,
        \Schoolbox\Fixtures\Division\User\Mapper $divisionUserMapper,
        \Schoolbox\Fixtures\Team\Player\Mapper $teamPlayerMapper,
        \Schoolbox\Fixtures\Team\Position\Mapper $teamPositionMapper,
        \Schoolbox\Fixtures\Coach\Mapper $coachMapper,
        \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper,
        \Schoolbox\UserMapper $userMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lock = $lock;
        $this->api = $api;
        $this->membership = $membership;
        $this->registry = $registry;
        $this->userLookup = $userLookup;
        $this->divisionUserMapper = $divisionUserMapper;
        $this->teamPlayerMapper = $teamPlayerMapper;
        $this->teamPositionMapper = $teamPositionMapper;
        $this->coachMapper = $coachMapper;
        $this->teamCoachMapper = $teamCoachMapper;
        $this->userMapper = $userMapper;
        $this->divisionCodec = new Codec\Division();
    }


    /**
     * Import Sports, Divisions, Teams, Team Members from clipboard,
     * creating them if they don't already exist
     *
     * @return void
     * @throws Exception
     * @throws LockAcquiringException
     * @throws LockConflictedException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* Consume data from Clipboard endpoint - each team comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'teams';

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        /* I guess have an ultralong season to basically remove it from typical usage
         * This season basically encompasses everything so we only need to spawn it once
         */
        $season = $this->registry->recallSeason();

        /* If --membership was supplied, also get the students
         * And (offensively) pass that setting as a bucket boolean
         *
         * Disgusting
         */
        $includeStudents = 'false';
        $includeStaff = 'false';
        $userLookup = [];

        if ($this->membership) {
            $includeStudents = 'true';
            $includeStaff = 'true';

            $userLookup = $this->userLookup->generate();
        }

        /* Bash through paginated data
         */
        $currentPage = 1;
        $lastPage = null;

        /* This will be set to something truthy, until the end of
         * the paginated data, at which point it becomes falsy
         */
        $nextUrl = $endpoint;

        while ($nextUrl && (null === $lastPage || $currentPage <= $lastPage) && $currentPage < self::MAX_CALLS) {
            $this->lock->refresh();

            $teams = $this->api->retrieve('GET', $endpoint, [
                'includePositions' => 'true',
                'includeStudents' => $includeStudents,
                'includeStaff' => $includeStaff,
                /* Bash through paginated data
                 */
                'page' => $currentPage,
                'pageLength' => self::NUM_PER_PAGE
            ]);

            foreach ($teams['data'] as $teamData) {
                $this->lock->refresh();

                /* Build a lookup here, because we're about to take advantage of it
                 */
                $positionLookup = [];

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

                    $positionLookup[$positionData['id']] = $position;

                    $this->log->debug(sprintf(
                        'Manufactured position %s (%s) in team %s',
                        $position->getName(),
                        $positionData['name'],
                        $team->getName()
                    ));
                }

                /* Slap in a team coach
                 */
                foreach ($teamData['assignedStaff'] as $assignedStaff) {
                    $this->assignCoach($this->findCoach($assignedStaff), $team);
                }

                /* If the --membership flag was set, ram in students and staff relationships too
                 */
                if ($this->membership) {
                    $this->importMemberships($userLookup, $positionLookup, $division, $team, $teamData);
                }
            }

            /* Bash through paginated data
             */
            $lastPage = $teams['pagination']['lastPage'];

            if ($currentPage >= $lastPage) {
                $nextUrl = null;
            }

            $currentPage++;
        }
    }


    /**
     * Import the students listed in $teamData into schoolbox Fixtures,
     * associating them with $team
     *
     * @param array                          $userLookup
     * @param array                          $positionLookup
     * @param Model                          $division
     * @param \Schoolbox\Fixtures\Team\Model $team
     * @param array                          $teamData
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws LockAcquiringException
     * @throws LockConflictedException
     * @throws \UnexpectedValueException
     */
    private function importMemberships(
        array $userLookup,
        array $positionLookup,
        \Schoolbox\Fixtures\Division\Model $division,
        \Schoolbox\Fixtures\Team\Model $team,
        array $teamData
    ): void {
        $this->log->debug(sprintf(
            'Processing memberships for team %s',
            $team->getName()
        ));

        /* Find out who this external_id is
         */
        $sequence = 0;

        foreach ($teamData['students'] as $student) {
            $this->lock->refresh();

            $externalId = $student['smsId'];
            $userId = $userLookup[$externalId] ?? null;
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
            $user = $this->userMapper->createModel()->setId($userId);

            /* Put them in a team
             */
            try {
                $teamPlayer = $this->teamPlayerMapper->findOneByColumnList([
                    'user_id' => $user->getId(),
                    'team_id' => $team->getId()
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $teamPlayer = $this->teamPlayerMapper->createModel()
                    ->setUser($user)
                    ->setTeam($team)
                    ->setSequence($sequence);

                if ($this->perform) {
                    $this->teamPlayerMapper->save($teamPlayer);
                }
            }

            $this->log->info(sprintf(
                'Assigned Clipboard human %s %s (%s) to team %s, using schoolbox user %s',
                $student['firstName'],
                $student['lastName'],
                $student['smsId'],
                $team->getName(),
                $userId
            ));

            /* Put them in the Division pool too
             * Check if they're already in there - just reuse it if it exists
             */
            try {
                $divisionUser = $this->divisionUserMapper->findOneByColumnList([
                    'user_id' => $user->getId(),
                    'division_id' => $division->getId()
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $divisionUser = $this->divisionUserMapper->createModel()
                    ->setUser($user)
                    ->setDivision($division)
                    ->setSequence($sequence);

                if ($this->perform) {
                    $this->divisionUserMapper->save($divisionUser);
                }
            }

            $this->log->debug(sprintf(
                'Assigned Clipboard human %s %s (%s) to player pool for division %s, using schoolbox user %s',
                $student['firstName'],
                $student['lastName'],
                $student['smsId'],
                $division->getName(),
                $userId
            ));

            /* Figure out their assigned position (if any)
             */
            if ('' !== trim((string)$student['positionId'])) {
                $position = $positionLookup[$student['positionId']];

                try {
                    $teamPosition = $this->teamPositionMapper->findOneByColumnList([
                        'team_id' => $team->getId(),
                        'position_id' => $position->getid(),
                        'user_id' => $user->getId()
                    ]);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $teamPosition = $this->teamPositionMapper->createModel()
                        ->setTeam($team)
                        ->setPosition($position)
                        ->setUser($user);

                    if ($this->perform) {
                        $this->teamPositionMapper->save($teamPosition);
                    }
                }

                $this->log->debug(sprintf(
                    'Assigning Clipboard student %s %s (%s) to position %s (clipboard %s) in team %s',
                    $student['firstName'],
                    $student['lastName'],
                    $student['smsId'],
                    $position->getName(),
                    $student['positionId'],
                    $team->getName()
                ));
            }
        }
    }


    /**
     * Assign a coach to a team, if they aren't already assigned
     *
     * @param \Schoolbox\Fixtures\Coach\Model $coach
     * @param \Schoolbox\Fixtures\Team\Model  $team
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function assignCoach(\Schoolbox\Fixtures\Coach\Model $coach, \Schoolbox\Fixtures\Team\Model $team): void
    {
        try {
            $teamCoach = $this->teamCoachMapper->findOneByColumnList([
                'team_id' => $team->getId(),
                'coach_id' => $coach->getId()
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->log->info(sprintf(
                'Assigning coach %s to team %s',
                $coach->getName(),
                $team->getName()
            ));

            $teamCoach = $this->teamCoachMapper->createModel()
                ->setTeam($team)
                ->setCoach($coach);

            if ($this->perform) {
                $this->teamCoachMapper->save($teamCoach);
            }
        }
    }


    /**
     * Find a coach for the given Clipboard data $coach
     * Creates a coach record if they couldn't be found
     *
     * @param array $coach
     * @return \Schoolbox\Fixtures\Coach\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function findCoach(array $coach): \Schoolbox\Fixtures\Coach\Model
    {
        $coachName = "{$coach['firstName']} {$coach['lastName']}";

        try {
            $coach = $this->coachMapper->findOneByColumnList([
                'name' => $coachName
            ]);

            $this->log->debug(sprintf(
                'Reusing existing coach %s',
                $coachName
            ));
        } catch (\Schoolbox\Mapper\Exception $e) {
            $coach = $this->coachMapper->createModel()
                ->setName($coachName);

            $this->log->debug(sprintf(
                'Creating new coach %s',
                $coachName
            ));
        }

        if ($this->perform) {
            $this->coachMapper->save($coach);
        }

        return $coach;
    }
}
