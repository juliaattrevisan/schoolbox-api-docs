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
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
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
        return $this->postToTeams(
            new Envelope(),
            $this->buildBuilder($match, $effector, self::TRIGGER_CREATED)
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

        // Send notifications for teams added/removed
        $teamChanges = $this->extractTeamChangesFromDiff($diff);
        foreach ($teamChanges['added'] as $addedTeamId) {
            // A team was added to this match
            $team = $this->teamMapper->findById($addedTeamId);
            $href = $this->buildHrefForMatch($match, $team);
            $this->postToTeam(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setHref($href)
                    ->setParam('match', $this->buildMatchParam($match, $team))
                    ->setTitle('Your team is now participating in :sport match :match'),
                $team
            );
        }
        foreach ($teamChanges['removed'] as $removedTeamId) {
            // A team was removed from this match
            $this->postToTeam(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setHref('#')
                    ->setParam('match', new Param($match->getName()))
                    ->setTitle('Your team is no longer participating in :sport match :match'),
                $this->teamMapper->findById($removedTeamId)
            );
        }

        if (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->postToTeams(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->postToTeams(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('start time', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->postToTeams(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
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
        return $this->postToParentsOfTeams(
            new Envelope(),
            $this->buildBuilder($match, $effector, self::TRIGGER_CREATED)
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

        // Send notifications for teams added/removed
        $teamChanges = $this->extractTeamChangesFromDiff($diff);
        foreach ($teamChanges['added'] as $addedTeamId) {
            // A team was added to this match
            $team = $this->teamMapper->findById($addedTeamId);
            $href = $this->buildHrefForMatch($match, $team);
            $this->postToParentsOfTeam(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setHref($href)
                    ->setParam('match', $this->buildMatchParam($match, $team))
                    ->setTitle(':child\'s team is now participating in :sport match :match'),
                $team
            );
        }
        foreach ($teamChanges['removed'] as $removedTeamId) {
            // A team was removed from this match
            $this->postToParentsOfTeam(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setHref('#')
                    ->setParam('match', new Param($match->getName()))
                    ->setTitle(':child\'s team is no longer participating in :sport match :match'),
                $this->teamMapper->findById($removedTeamId)
            );
        }

        if (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->postToParentsOfTeams(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->postToParentsOfTeams(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('start time', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->postToParentsOfTeams(
                $envelope,
                $this->buildBuilder($match, $effector, self::TRIGGER_UPDATED)
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
     * Extracts the teams that have been added and removed from the match.
     *
     * @param array $diff
     * @return array
     */
    protected function extractTeamChangesFromDiff($diff)
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
     * @param Model  $match
     * @param User   $effector
     * @param string $trigger
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
        $ts = \Alaress_Date_Format::now()->getUnixTimeStamp();

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
        $ts = \Alaress_Date_Format::now()->getUnixTimeStamp();

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
     * @param Model $match
     * @param TeamModel $team
     * @return string
     */
    protected function buildHrefForMatch(MatchModel $match, TeamModel $team)
    {
        return "/sportsfixtures/matches/{$match->getId()}/results/{$team->getId()}";
    }

    /**
     * Builds a parameter for this notifier's builder's "match" parameter.
     *
     * @param Model $match
     * @param TeamModel $team
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
