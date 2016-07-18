<?php
/**
 * Notify parents/players of upcoming matches
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Match;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\SportsFixtures\Team\Model as TeamModel;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Team\Player\Model as TeamPlayerModel;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\Match\Model as MatchModel;
use Schoolbox\User;
use Monolog\Logger;

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
                $bundle->attach(self::TRIGGER_CREATED, $notifyTime);
                break;
            case Mapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, $notifyTime);
                break;
        }
    }

    /**
     * Setup upcoming match notification when new match created (Students)
     *
     * @param User $effector
     * @param MatchModel $match
     * @return Envelope
     */
    public function notifyStudentCreated(MatchModel $match, User $effector)
    {
        $match = $this->hydrateMatch($match);
        return $this->targetToTeams(
            new Envelope(),
            $this->buildNotificationBuilder($match, $effector, self::TRIGGER_CREATED)
                ->setTitle('You have an upcoming :sport match :match'),
            $match
        );
    }

    /**
     * Setup upcoming match notification when existing match updated (Students)
     *
     * @param User $effector
     * @param MatchModel $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(MatchModel $match, User $effector, array $diff = null)
    {
        $envelope = new Envelope();
        $match    = $this->hydrateMatch($match);

        if (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->targetToTeams(
                $envelope,
                $this->buildNotificationBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new venue'),
                $match
            );
        }

        if (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->targetToTeams(
                $envelope,
                $this->buildNotificationBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new start time'),
                $match
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->targetToTeams(
                $envelope,
                $this->buildNotificationBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has been updated'),
                $match
            );
        }

        return $envelope;
    }

    /**
     * Setup upcoming match notification when new match created (Parents of players)
     *
     * @param User $effector
     * @param MatchModel $match
     * @return Envelope
     */
    public function notifyParentCreated(MatchModel $match, User $effector)
    {
        $match = $this->hydrateMatch($match);
        return $this->targetToParentsOfTeams(
            new Envelope(),
            $this->buildNotificationBuilder($match, $effector, self::TRIGGER_CREATED)
                ->setTitle('Upcoming :sport match :match for :child'),
            $match
        );
    }

    /**
     * Setup upcoming match notification when existing match updated (Parents of players)
     *
     * @param User $effector
     * @param MatchModel $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentUpdated(MatchModel $match, User $effector, array $diff = null)
    {
        $envelope = new Envelope();
        $match    = $this->hydrateMatch($match);

        if (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->targetToParentsOfTeams(
                $envelope,
                $this->buildNotificationBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new venue'),
                $match
            );
        }

        if (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->targetToParentsOfTeams(
                $envelope,
                $this->buildNotificationBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new start time'),
                $match
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->targetToParentsOfTeams(
                $envelope,
                $this->buildNotificationBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has been updated'),
                $match
            );
        }

        return $envelope;
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
     * Creates a generic notification builder for this match.
     *
     * @param Model  $match
     * @param User   $effector
     * @param string $trigger
     * @return Builder
     */
    protected function buildNotificationBuilder(MatchModel $match, User $effector, $trigger)
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
     * Creates a generic notification to a match's team's players.
     *
     * This can then be targeted to the parents of the players, or the players themselves.
     *
     * @param Model     $match
     * @param TeamModel $team
     * @param User      $effector
     * @param string    $trigger
     * @return Builder
     */
    protected function buildNotificationBuilderForTeam(MatchModel $match, TeamModel $team, User $effector, $trigger)
    {
        $ts   = \Alaress_Date_Format::now()->getUnixTimeStamp();
        $href = "/sportsfixtures/matches/{$match->getId()}/results/{$team->getId()}";
        $bob  = new Builder();
        $bob->setHref($href)
            ->setParam('match', new Param($match->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE))
            ->setParam('sport', new Param($match->getDivision()->getSport()->getName(), $match->getDivision()->getSport()->getUrl()))
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $match->getId(), $trigger) ])
            ->setNamespace($this->getSlug())
        ;
        return $bob;
    }

    /**
     * Targets this notification to an entire team.
     *
     * @param Envelope  $envelope
     * @param Builder   $bob
     * @param TeamModel $team
     * @return Envelope
     */
    protected function targetToTeam(Envelope $envelope, Builder $bob, TeamModel $team)
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
     * Targets this notification to the parents of an entire team.
     *
     * @param Envelope  $envelope
     * @param Builder   $bob
     * @param TeamModel $team
     * @return Envelope
     */
    protected function targetToParentsOfTeam(Envelope $envelope, Builder $bob, TeamModel $team)
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
     * Targets this notification to both teams.
     *
     * @param Envelope   $envelope
     * @param Builder    $bob
     * @param MatchModel $match
     * @return Envelope
     */
    protected function targetToTeams(Envelope $envelope, Builder $bob, MatchModel $match)
    {
        $ts = \Alaress_Date_Format::now()->getUnixTimeStamp();

        // Home
        $href = "/sportsfixtures/matches/{$match->getId()}/results/{$match->getHomeTeam()->getId()}";
        $bob->setHref($href)
            ->setParam('match', new Param($match->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE));
        $this->targetToTeam($envelope, $bob, $match->getHomeTeam());

        // and away
        $href = "/sportsfixtures/matches/{$match->getId()}/results/{$match->getAwayTeam()->getId()}";
        $bob->setHref($href)
            ->setParam('match', new Param($match->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE));
        $this->targetToTeam($envelope, $bob, $match->getAwayTeam());

        return $envelope;
    }

    /**
     * Targets this notification to the parents of both teams.
     *
     * @param Envelope   $envelope
     * @param Builder    $bob
     * @param MatchModel $match
     * @return Envelope
     */
    protected function targetToParentsOfTeams(Envelope $envelope, Builder $bob, MatchModel $match)
    {
        $ts = \Alaress_Date_Format::now()->getUnixTimeStamp();

        // Home
        $href = "/sportsfixtures/matches/{$match->getId()}/results/{$match->getHomeTeam()->getId()}";
        $bob->setHref($href)
            ->setParam('match', new Param($match->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE));
        $this->targetToParentsOfTeam($envelope, $bob, $match->getHomeTeam());

        // and away
        $href = "/sportsfixtures/matches/{$match->getId()}/results/{$match->getAwayTeam()->getId()}";
        $bob->setHref($href)
            ->setParam('match', new Param($match->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE));
        $this->targetToParentsOfTeam($envelope, $bob, $match->getAwayTeam());

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
}
