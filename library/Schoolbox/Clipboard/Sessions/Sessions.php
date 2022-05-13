<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Hierarchy\Lookup\Person;
use Schoolbox\Clipboard\Hierarchy\Registry;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;
use Symfony\Component\Lock\LockInterface;

/**
 * Generate a list of Clipboard Sessions to load as schoolbox Matchups
 */
class Sessions implements IStrategy
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
     * A placeholder venue if one wasn't specified by Clipboard
     */
    private const UNSPECIFIED_LOCATION = 'Unspecified location';


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
     * @var Registry
     */
    private Registry $registry;


    /**
     * @var Person
     */
    private \Schoolbox\Clipboard\Hierarchy\Lookup\Person $userLookup;


    /**
     * @var \Schoolbox\Fixtures\Venue\Mapper
     */
    private \Schoolbox\Fixtures\Venue\Mapper $venueMapper;


    /**
     * @var \Schoolbox\Fixtures\Matchup\Mapper
     */
    private \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper;


    /**
     * @var \Schoolbox\Fixtures\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Coach\Mapper $coachMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Coach\Mapper
     */
    private \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper;


    /**
     * @var \Schoolbox\Clipboard\Hierarchy\Codec\Division
     */
    private \Schoolbox\Clipboard\Hierarchy\Codec\Division $divisionCodec;


    /**
     * Constructor.
     *
     * @param LoggerInterface                       $log
     * @param bool                                  $perform
     * @param LockInterface                         $lock
     * @param IApi                                  $api
     * @param Registry                              $registry
     * @param Person                                $userLookup
     * @param \Schoolbox\Fixtures\Venue\Mapper      $venueMapper
     * @param \Schoolbox\Fixtures\Matchup\Mapper    $matchupMapper
     * @param \Schoolbox\Fixtures\Coach\Mapper      $coachMapper
     * @param \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        LockInterface $lock,
        IApi $api,
        Registry $registry,
        Person $userLookup,
        \Schoolbox\Fixtures\Venue\Mapper $venueMapper,
        \Schoolbox\Fixtures\Matchup\Mapper $matchupMapper,
        \Schoolbox\Fixtures\Coach\Mapper $coachMapper,
        \Schoolbox\Fixtures\Team\Coach\Mapper $teamCoachMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->lock = $lock;
        $this->api = $api;
        $this->registry = $registry;
        $this->userLookup = $userLookup;
        $this->venueMapper = $venueMapper;
        $this->matchupMapper = $matchupMapper;
        $this->coachMapper = $coachMapper;
        $this->teamCoachMapper = $teamCoachMapper;
        $this->divisionCodec = new \Schoolbox\Clipboard\Hierarchy\Codec\Division();
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        /* TODO: Clipboard is going to provide external ids for us to find
         *  They would appear here, but for now this will be unused
         */
        $userLookup = $this->userLookup->generate();

        /* Consume data from Clipboard endpoint - each Session comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'sessions';

        $this->log->info('');

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        /* I guess have an ultralong season to basically remove it from typical usage
         * This season basically encompasses everything so we only need to spawn it once
         */
        $season = $this->registry->recallSeason();

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

            $sessions = $this->api->retrieve('GET', $endpoint, [
                'includeTeams' => 'true',
                'includeStaff' => 'true',
                'includeRoundName' => 'true',
                'includeStatuses' => ['confirmed'],
                /* Apparently this should give you "only not cancelled" sessions
                 */
                'cancelled' => 'false',
                /* Bash through paginated data
                 */
                'page' => $currentPage,
                'pageLength' => self::NUM_PER_PAGE
            ]);

            foreach ($sessions['data'] as $session) {
                /* Sport identifiers
                 */
                $teamActivityId = (int)$session['activity']['id'];
                $teamActivity = $session['activity']['name'] ?? '';

                foreach ($session['teams'] as $teamData) {
                    $this->lock->refresh();

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

                    /* Set up a Matchup instance for this team; they aren't vsing anyone
                     */
                    $venue = $this->findVenue($session['locationData']['name'] ?? null);

                    $matchupName = trim($session['title']);

                    $startAt = \Alaress_Date_Format::newFromTimestamp(strtotime($session['startDateTime']));
                    $finishAt = \Alaress_Date_Format::newFromTimestamp(strtotime($session['endDateTime']));

                    /* See if this matchup exists by taking the bestest guess
                     */
                    try {
                        $matchup = $this->matchupMapper->findOneByColumnList([
                            'name' => $matchupName,
                            'division_id' => $division->getId(),
                            'season_id' => $season->getId(),
                            'home_team_id' => $team->getId(),
                            'start_at' => $startAt->getDatabase(),
                            'finish_at' => $finishAt->getDatabase(),
                        ]);

                        $this->log->debug(sprintf(
                            'Reusing matchup %s on %s, at venue %s',
                            $matchup->getDisplayName(),
                            $matchup->getStartAt()->getDatabase(),
                            null === $matchup->getVenue() ? self::UNSPECIFIED_LOCATION : $matchup->getVenue()->getName()
                        ));
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $matchup = $this->matchupMapper->createModel()
                            ->setName($matchupName)
                            ->setSeason($season)
                            ->setDivision($division)
                            ->setVenue($venue)
                            ->setHomeTeam($team)
                            ->setStartAt($startAt)
                            ->setFinishAt($finishAt);

                        $this->log->debug(sprintf(
                            'Creating matchup %s on %s, at venue %s',
                            $matchup->getDisplayName(),
                            $matchup->getStartAt()->getDatabase(),
                            null === $matchup->getVenue() ? self::UNSPECIFIED_LOCATION : $matchup->getVenue()->getName()
                        ));
                    }

                    /* Slap in a team coach
                     */
                    foreach ($session['assignedStaff'] as $assignedStaff) {
                        $this->assignCoach($this->findCoach($assignedStaff), $team);
                    }

                    /* We saw this, so we keep this
                     */
                    if ($this->perform) {
                        $this->matchupMapper->save($matchup);
                    }
                }
            }

            /* Bash through paginated data
             */
            $lastPage = $sessions['pagination']['lastPage'];

            if ($currentPage >= $lastPage) {
                $nextUrl = null;
            }

            $currentPage++;
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
        } catch (\Schoolbox\Mapper\Exception $e) {
            $coach = $this->coachMapper->createModel()
                ->setName($coachName);
        }

        if ($this->perform) {
            $this->coachMapper->save($coach);
        }

        return $coach;
    }


    /**
     * Find a way to generate a corresponding Venue
     * If a venue name was not specified, generate a placeholder
     * (venues are optional in clipboard)
     *
     * @param string|null $venueName
     * @return \Schoolbox\Fixtures\Venue\Model|null
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    private function findVenue(?string $venueName): ?\Schoolbox\Fixtures\Venue\Model
    {
        /* If venue name wasn't specified, find a placeholder
         * generating one if it doesn't already exist
         */
        if ('' === trim((string)$venueName)) {
            return null;
        }

        /* Set up a Venue instance for this Matchup,
         * reusing an existing one if possible
         */
        try {
            $venue = $this->venueMapper->findOneByColumn('name', $venueName);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $venue = $this->venueMapper->createModel()
                ->setName($venueName);

            if ($this->perform) {
                $this->venueMapper->save($venue);
            }
        }

        return $venue;
    }
}
