<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\ItineraryItem;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
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
 * Notify parents/players of changes to a match's itinerary.
 *
 * @package Schoolbox\SportsFixtures\ItineraryItem
 */
class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';

    /**
     * @param null $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['matchMapper'])) {
            $dependencies['matchMapper'] = MatchMapper::getInstance();
        }
        if (empty($dependencies['divisionMapper'])) {
            $dependencies['divisionMapper'] = DivisionMapper::getInstance();
        }
        if (empty($dependencies['sportMapper'])) {
            $dependencies['sportMapper'] = SportMapper::getInstance();
        }
        if (empty($dependencies['teamPlayerMapper'])) {
            $dependencies['teamPlayerMapper'] = TeamPlayerMapper::getInstance();
        }
        if (empty($dependencies['teamMapper'])) {
            $dependencies['teamMapper'] = TeamMapper::getInstance();
        }

        parent::loadDependencies($dependencies);
    }

    /**
     * @return string
     */
    protected function getSlug()
    {
        return NSMapper::NS_SPORTS_FIXTURES;
    }

    /**
     * @param TriggerBundleInterface $bundle
     * @return void
     */
    public function bundleTriggers($bundle)
    {
        $method = $bundle->getEvent()->getAction();

        /** @var MatchModel $match */
        $match = $bundle->getEvent()->getObject();

        // Notify 24 hours before the match start time
        $notifyTime = $match->getStartAt()->addHours(-24)->getUnixTimeStamp();

        switch ($method) {
            case Mapper::METHOD_CREATE:
            case Mapper::METHOD_UPDATE:
            case Mapper::METHOD_DELETE:
                $bundle->attach(self::TRIGGER_UPDATED, $notifyTime);
                break;
        }
    }

    /**
     * Setup upcoming notification when itinerary changes
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    public function notifyStudentUpdated(Model $itinerary, User $effector)
    {
        // Generic notification to team's participants that itinerary has updated
        $match = $this->hydrateMatch($itinerary->getMatch());
        return $this->postToTeams(
            new Envelope(),
            $this->buildBuilder($match, $effector, self::TRIGGER_CREATED)
                ->setTitle('Your upcoming :sport match :match itinerary has been changed'),
            $match
        );
    }

    /**
     * Setup upcoming notification when itinerary changes
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    public function notifyParentUpdated(Model $itinerary, User $effector)
    {
        // Generic notification to team's participants that itinerary has updated
        $match = $this->hydrateMatch($itinerary->getMatch());
        return $this->postToParentsOfTeams(
            new Envelope(),
            $this->buildBuilder($match, $effector, self::TRIGGER_CREATED)
                 ->setTitle('Your upcoming :sport match :match itinerary has been changed'),
            $match
        );
    }

    /**
     * Here be helper methods
     */

    /**
     * @param MatchModel $match
     * @return MatchModel
     */
    protected function hydrateMatch(MatchModel $match)
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
     * Builds a generic notification builder for this match.
     *
     * @param MatchModel $match
     * @param User       $effector
     * @param string     $trigger
     * @return Builder
     */
    protected function buildBuilder(MatchModel $match, User $effector, $trigger)
    {
        $bob = new Builder();
        $bob->setParam('sport', new Param($match->getDivision()->getSport()->getName(), $match->getDivision()->getSport()->getUrl()))
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $match->getId(), $trigger) ])
            ->setNamespace($this->getSlug())
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
    protected function postToTeam(Envelope $envelope, Builder $bob, TeamModel $team)
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
    protected function postToParentsOfTeam(Envelope $envelope, Builder $bob, TeamModel $team)
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
    protected function postToTeams(Envelope $envelope, Builder $bob, MatchModel $match)
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
    protected function postToParentsOfTeams(Envelope $envelope, Builder $bob, MatchModel $match)
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
     * @param TeamModel  $team
     * @return string
     */
    protected function buildHrefForMatch(MatchModel $match, TeamModel $team)
    {
        return "/sportsfixtures/matches/{$match->getId()}/results/{$team->getId()}";
    }

    /**
     * Builds a parameter for this notifier's builder's "match" parameter.
     *
     * @param MatchModel $match
     * @param TeamModel  $team
     * @param int $stacking
     * @return Param
     */
    protected function buildMatchParam(MatchModel $match, TeamModel $team, $stacking = Param::STACKING_REPLACE)
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
