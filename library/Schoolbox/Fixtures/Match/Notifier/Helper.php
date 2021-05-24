<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Match\Notifier;

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Match\Model as Match;
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
     * @var MatchMapper
     */
    protected $matchMapper;

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
     * @param MatchMapper      $matchMapper
     * @param DivisionMapper   $divisionMapper
     * @param SportMapper      $sportMapper
     * @param TeamPlayerMapper $teamPlayerMapper
     * @param TeamMapper       $teamMapper
     */
    public function __construct(
        MatchMapper $matchMapper,
        DivisionMapper $divisionMapper,
        SportMapper $sportMapper,
        TeamPlayerMapper $teamPlayerMapper,
        TeamMapper $teamMapper
    ) {
        $this->matchMapper      = $matchMapper;
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
     * @param Match $match
     * @return Match
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function hydrateMatch(Match $match)
    {
        /** @var Match $hydratedMatch */
        $hydratedMatch = $this->matchMapper->findById($match->getId());
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
     * @param Match      $match
     * @param User       $effector
     * @param string     $namespace
     * @param string     $trigger
     * @return Builder
     */
    public function buildBuilder(Match $match, User $effector, $namespace, $trigger)
    {
        $bob = new Builder($effector);
        $bob->setSportParam($match->getDivision()->getSport())
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $namespace, $match->getId(), $trigger) ])
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
     * @param Match    $match
     * @param int      $stacking The stacking setting to use for the match parameter
     * @return Envelope
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function postToTeams(Envelope $envelope, Builder $bob, Match $match, $stacking = Param::STACKING_REPLACE)
    {
        foreach ($match->getAllTeams() as $team) {
            $bob->setMatchHref($match, $team)
                ->setMatchParam($match, $team, $stacking);
            $this->postToTeam($envelope, $bob, $team);
        }
        return $envelope;
    }

    /**
     * Posts an envelope and contents to all parents of players of both a match's teams.
     *
     * @param Envelope $envelope
     * @param Builder  $bob
     * @param Match    $match
     * @param int      $stacking The stacking setting to use for the match parameter
     * @return Envelope
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     */
    public function postToParentsOfTeams(Envelope $envelope, Builder $bob, Match $match, $stacking = Param::STACKING_REPLACE)
    {
        foreach ($match->getAllTeams() as $team) {
            $bob->setMatchHref($match, $team)
                ->setMatchParam($match, $team, $stacking);
            $this->postToParentsOfTeam($envelope, $bob, $team);
        }
        return $envelope;
    }
}
