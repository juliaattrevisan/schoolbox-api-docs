<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     */
    public function findTeam($teamId)
    {
        return $this->teamMapper->findById($teamId);
    }

    /**
     * @param Match $match
     * @return Match
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
            list($newTeam, $oldTeam) = $diff['homeTeam'];
            $oldTeams[] = $oldTeam;
            $newTeams[] = $newTeam;
        }
        if (isset($diff['awayTeam'])) {
            list($newTeam, $oldTeam) = $diff['awayTeam'];
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
     * @param Envelope  $envelope
     * @param Builder   $bob
     * @param Team $team
     * @return Envelope
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
     * @param Envelope  $envelope
     * @param Builder   $bob
     * @param Team $team
     * @return Envelope
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
     * @param Envelope   $envelope
     * @param Builder    $bob
     * @param Match      $match
     * @param int        $stacking The stacking setting to use for the match parameter
     * @return Envelope
     */
    public function postToTeams(Envelope $envelope, Builder $bob, Match $match, $stacking = Param::STACKING_REPLACE)
    {
        // Home
        if ($match->getHomeTeam()) {
            $bob->setMatchHref($match, $match->getHomeTeam())
                ->setMatchParam($match, $match->getHomeTeam(), $stacking);
            $this->postToTeam($envelope, $bob, $match->getHomeTeam());
        }

        // and away
        if ($match->getAwayTeam()) {
            $bob->setMatchHref($match, $match->getAwayTeam())
                ->setMatchParam($match, $match->getAwayTeam(), $stacking);
            $this->postToTeam($envelope, $bob, $match->getAwayTeam());
        }
        return $envelope;
    }

    /**
     * Posts an envelope and contents to all parents of players of both a match's teams.
     *
     * @param Envelope   $envelope
     * @param Builder    $bob
     * @param Match      $match
     * @param int        $stacking The stacking setting to use for the match parameter
     * @return Envelope
     */
    public function postToParentsOfTeams(Envelope $envelope, Builder $bob, Match $match, $stacking = Param::STACKING_REPLACE)
    {
        // Home
        $bob->setMatchHref($match, $match->getHomeTeam())
            ->setMatchParam($match, $match->getHomeTeam(), $stacking);
        $this->postToParentsOfTeam($envelope, $bob, $match->getHomeTeam());

        // and away
        $bob->setMatchHref($match, $match->getAwayTeam())
            ->setMatchParam($match, $match->getAwayTeam(), $stacking);
        $this->postToParentsOfTeam($envelope, $bob, $match->getAwayTeam());

        return $envelope;
    }

    /**
     * Here be the mappers, nothing interesting to see past this point... (i promise)
     *
     *               ,---.
     *            ,.'-.   \
     *           ( ( ,'"""""-.
     *           `,X          `.
     *           /` `           `._
     *          (            ,   ,_\
     *          |          ,---.,'o `.
     *          |         / o   \     )
     *           \ ,.    (      .____,
     *            \| \    \____,'     \
     *          '`'\  \        _,____,'
     *          \  ,--      ,-'     \
     *            ( C     ,'         \
     *             `--'  .'           |
     *               |   |         .O |
     *             __|    \        ,-'_
     *            / `L     `._  _,'  ' `.
     *           /    `--.._  `',.   _\  `
     *           `-.       /\  | `. ( ,\  \
     *          _/  `-._  /  \ |--'  (     \
     *         '  `-.   `'    \/\`.   `.    )
     *               \      -    \ `.  |    |
     *
     */

    /** @var MatchMapper $matchMapper */
    protected $matchMapper;

    /** @var DivisionMapper $divisionMapper */
    protected $divisionMapper;

    /** @var SportMapper $sportMapper */
    protected $sportMapper;

    /** @var TeamPlayerMapper $teamPlayerMapper */
    protected $teamPlayerMapper;

    /** @var TeamMapper $teamMapper */
    protected $teamMapper;

    /**
     * @param $mapper
     */
    protected function setMatchMapper($mapper)
    {
        $this->matchMapper = $mapper;
    }

    /**
     * @param $mapper
     */
    protected function setDivisionMapper($mapper)
    {
        $this->divisionMapper = $mapper;
    }

    /**
     * @param $mapper
     */
    protected function setSportMapper($mapper)
    {
        $this->sportMapper = $mapper;
    }

    /**
     * @param $mapper
     */
    protected function setTeamPlayerMapper($mapper)
    {
        $this->teamPlayerMapper = $mapper;
    }

    /**
     * @param $mapper
     */
    protected function setTeamMapper($mapper)
    {
        $this->teamMapper = $mapper;
    }
}
