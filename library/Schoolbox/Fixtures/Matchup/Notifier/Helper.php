<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Matchup\Notifier;

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Notification\Instance\Builder;
use Schoolbox\User;
use Monolog\Logger;

/**
 * Helper for the match notifier.
 *
 * @package Schoolbox\Fixtures\Match\Notifier
 */
class Helper
{

    /**
     * @var MatchupMapper
     */
    protected $matchupMapper;

    /**
     * @var DivisionMapper
     */
    protected $divisionMapper;

    /**
     * @var SportMapper
     */
    protected $sportMapper;

    /**
     * @var TeamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @var TeamMapper
     */
    protected $teamMapper;


    /**
     * Constructor
     *
     * @param MatchupMapper    $matchupMapper
     * @param DivisionMapper   $divisionMapper
     * @param SportMapper      $sportMapper
     * @param TeamPlayerMapper $teamPlayerMapper
     * @param TeamMapper       $teamMapper
     */
    public function __construct(
        MatchupMapper $matchupMapper,
        DivisionMapper $divisionMapper,
        SportMapper $sportMapper,
        TeamPlayerMapper $teamPlayerMapper,
        TeamMapper $teamMapper
    ) {
        $this->matchupMapper    = $matchupMapper;
        $this->divisionMapper   = $divisionMapper;
        $this->sportMapper      = $sportMapper;
        $this->teamPlayerMapper = $teamPlayerMapper;
        $this->teamMapper       = $teamMapper;
    }

    /**
     * Retrieves a team given a team ID.
     *
     * @param int $teamId
     * @return Team
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function findTeam($teamId)
    {
        return $this->teamMapper->findById($teamId);
    }

    /**
     * @param Matchup $matchup
     * @return Matchup
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function hydrateMatch(Matchup $matchup)
    {
        /** @var Matchup $hydratedMatch */
        $hydratedMatch = $this->matchupMapper->findById($matchup->getId());
        $division      = $this->divisionMapper->findById($hydratedMatch->getDivision()->getId());
        $sport         = $this->sportMapper->findById($division->getSport()->getId());
        $division->setSport($sport);
        $hydratedMatch->setDivision($division);
        return $hydratedMatch;
    }

    /**
     * Extracts the teams that have been added and removed from the match.
     *
     * @param array $diff
     * @return array
     */
    public function extractTeamChangesFromDiff($diff)
    {
        $oldTeams = [];
        $newTeams = [];
        if (isset($diff['homeTeam'])) {
            [$newTeam, $oldTeam] = $diff['homeTeam'];
            $oldTeams[] = $oldTeam;
            $newTeams[] = $newTeam;
        }
        if (isset($diff['awayTeam'])) {
            [$newTeam, $oldTeam] = $diff['awayTeam'];
            $oldTeams[] = $oldTeam;
            $newTeams[] = $newTeam;
        }
        $oldTeams = array_filter($oldTeams);
        $newTeams = array_filter($newTeams);

        // Removed teams: old - new
        return [
            'added'   => array_diff($newTeams, $oldTeams),
            'removed' => array_diff($oldTeams, $newTeams),
        ];
    }

    /**
     * Builds a generic notification builder for this match.
     *
     * @param Matchup    $matchup
     * @param User       $effector
     * @param string     $namespace
     * @param string     $trigger
     * @return Builder
     */
    public function buildBuilder(Matchup $matchup, User $effector, $namespace, $trigger)
    {
        $bob = new Builder($effector);
        $bob->setSportParam($matchup->getDivision()->getSport())
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $namespace, $matchup->getId(), $trigger) ])
            ->setNamespace($namespace)
        ;
        return $bob;
    }

    /**
     * Posts an envelope and contents to all players of a team.
     *
     * @param Envelope $envelope
     * @param Builder  $bob
     * @param Team     $team
     * @return Envelope
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function postToTeam(Envelope $envelope, Builder $bob, Team $team)
    {
        $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $team->getId());
        foreach ($teamPlayers as $teamPlayer) {
            $teamUser = $teamPlayer->getUser();
            if ($teamUser instanceof User) {
                $envelope->targetToUser($teamUser, $bob->build());
            }
        }
        return $envelope;
    }

    /**
     * Posts an envelope and contents to all parents of players of a team.
     *
     * @param Envelope $envelope
     * @param Builder  $bob
     * @param Team     $team
     * @return Envelope
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function postToParentsOfTeam(Envelope $envelope, Builder $bob, Team $team)
    {
        $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $team->getId());
        foreach ($teamPlayers as $teamPlayer) {
            $teamUser = $teamPlayer->getUser();
            if ($teamUser instanceof User) {
                $bob->setParam('child', new UserParam($teamUser));
                $bob->putMatchMeta('studentExternalId', $teamPlayer->getUser()->getExternalId());
                $envelope->targetToParent($teamUser, $bob->build());
            }
        }
        return $envelope;
    }

    /**
     * Posts an envelope and contents to all players of both a match's teams.
     *
     * @param Envelope $envelope
     * @param Builder  $bob
     * @param Matchup  $matchup
     * @param int      $stacking The stacking setting to use for the match parameter
     * @return Envelope
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function postToTeams(Envelope $envelope, Builder $bob, Matchup $matchup, $stacking = Param::STACKING_REPLACE)
    {
        foreach ($matchup->getAllTeams() as $team) {
            $bob->setMatchHref($matchup, $team)
                ->setMatchParam($matchup, $team, $stacking);
            $this->postToTeam($envelope, $bob, $team);
        }
        return $envelope;
    }

    /**
     * Posts an envelope and contents to all parents of players of both a match's teams.
     *
     * @param Envelope $envelope
     * @param Builder  $bob
     * @param Matchup  $matchup
     * @param int      $stacking The stacking setting to use for the match parameter
     * @return Envelope
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function postToParentsOfTeams(Envelope $envelope, Builder $bob, Matchup $matchup, $stacking = Param::STACKING_REPLACE)
    {
        foreach ($matchup->getAllTeams() as $team) {
            $bob->setMatchHref($matchup, $team)
                ->setMatchParam($matchup, $team, $stacking);
            $this->postToParentsOfTeam($envelope, $bob, $team);
        }
        return $envelope;
    }
}
