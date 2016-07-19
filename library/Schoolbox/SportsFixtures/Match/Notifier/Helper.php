<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Match\Notifier;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;

use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Model as TeamModel;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\Match\Model as MatchModel;
use Schoolbox\User;
use Monolog\Logger;

/**
 * Helper for the match notifier.
 *
 * @package Schoolbox\SportsFixtures\Match\Notifier
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
     * @return TeamModel
     */
    public function findTeam($teamId)
    {
        return $this->teamMapper->findById($teamId);
    }

    /**
     * @param MatchModel $match
     * @return MatchModel
     */
    public function hydrateMatch(MatchModel $match)
    {
        /** @var MatchModel $hydratedMatch */
        $hydratedMatch = $this->matchMapper->findById($match->getId());
        $division      = $this->divisionMapper->findById($match->getDivision()->getId());
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
     * @param MatchModel $match
     * @param User       $effector
     * @param string     $namespace
     * @param string     $trigger
     * @return Builder
     */
    public function buildBuilder(MatchModel $match, User $effector, $namespace, $trigger)
    {
        $bob = new Builder();
        $bob->setParam('sport', new Param($match->getDivision()->getSport()->getName(), $match->getDivision()->getSport()->getUrl()))
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
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
     * @param TeamModel $team
     * @return Envelope
     */
    public function postToTeam(Envelope $envelope, Builder $bob, TeamModel $team)
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
     * @param TeamModel $team
     * @return Envelope
     */
    public function postToParentsOfTeam(Envelope $envelope, Builder $bob, TeamModel $team)
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
     * @param MatchModel $match
     * @return Envelope
     */
    public function postToTeams(Envelope $envelope, Builder $bob, MatchModel $match)
    {
        // Home
        $href = $this->buildHrefForMatch($match, $match->getHomeTeam());
        $bob->setHref($href)
            ->setParam('match', $this->buildMatchParam($match, $match->getHomeTeam()));
        $this->postToTeam($envelope, $bob, $match->getHomeTeam());

        // and away
        $href = $this->buildHrefForMatch($match, $match->getAwayTeam());
        $bob->setHref($href)
            ->setParam('match', $this->buildMatchParam($match, $match->getAwayTeam()));
        $this->postToTeam($envelope, $bob, $match->getAwayTeam());

        return $envelope;
    }

    /**
     * Posts an envelope and contents to all parents of players of both a match's teams.
     *
     * @param Envelope   $envelope
     * @param Builder    $bob
     * @param MatchModel $match
     * @return Envelope
     */
    public function postToParentsOfTeams(Envelope $envelope, Builder $bob, MatchModel $match)
    {
        // Home
        $href = $this->buildHrefForMatch($match, $match->getHomeTeam());
        $bob->setHref($href)
            ->setParam('match', $this->buildMatchParam($match, $match->getHomeTeam()));
        $this->postToParentsOfTeam($envelope, $bob, $match->getHomeTeam());

        // and away
        $href = $this->buildHrefForMatch($match, $match->getAwayTeam());
        $bob->setHref($href)
            ->setParam('match', $this->buildMatchParam($match, $match->getAwayTeam()));
        $this->postToParentsOfTeam($envelope, $bob, $match->getAwayTeam());

        return $envelope;
    }

    /**
     * Builds the href for the results page for this match and team.
     *
     * @param MatchModel $match
     * @param TeamModel $team
     * @return string
     */
    public function buildHrefForMatch(MatchModel $match, TeamModel $team)
    {
        return "/sportsfixtures/matches/{$match->getId()}/results/{$team->getId()}";
    }

    /**
     * Builds a parameter for this notifier's builder's "match" parameter.
     *
     * @param MatchModel $match
     * @param TeamModel $team
     * @param int $stacking
     * @return Param
     */
    public function buildMatchParam(MatchModel $match, TeamModel $team, $stacking = Param::STACKING_REPLACE)
    {
        $ts   = \Alaress_Date_Format::now()->getUnixTimeStamp();
        $href = $this->buildHrefForMatch($match, $team);
        return new Param($match->getName(), "{$href}?{$ts}", $stacking);
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
