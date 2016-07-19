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
        $dependencies['notifierHelper'] = new Notifier\Helper(
            MatchMapper::getInstance(),
            DivisionMapper::getInstance(),
            SportMapper::getInstance(),
            TeamPlayerMapper::getInstance(),
            TeamMapper::getInstance()
        );
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
        $match = $this->notifierHelper->hydrateMatch($match);
        return $this->notifierHelper->postToTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_CREATED)
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
        $match    = $this->notifierHelper->hydrateMatch($match);

        // Send notifications for teams added/removed
        $teamChanges = $this->notifierHelper->extractTeamChangesFromDiff($diff);
        foreach ($teamChanges['added'] as $addedTeamId) {
            // A team was added to this match
            $team = $this->teamMapper->findById($addedTeamId);
            $href = $this->notifierHelper->buildHrefForMatch($match, $team);
            $this->notifierHelper->postToTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setHref($href)
                    ->setParam('match', $this->notifierHelper->buildMatchParam($match, $team))
                    ->setTitle('Your team is now participating in :sport match :match'),
                $team
            );
        }
        foreach ($teamChanges['removed'] as $removedTeamId) {
            // A team was removed from this match
            $this->notifierHelper->postToTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setHref('#')
                    ->setParam('match', new Param($match->getName()))
                    ->setTitle('Your team is no longer participating in :sport match :match'),
                $this->teamMapper->findById($removedTeamId)
            );
        }

        if (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('start time', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
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
        $match = $this->notifierHelper->hydrateMatch($match);
        return $this->notifierHelper->postToParentsOfTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_CREATED)
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
        $match    = $this->notifierHelper->hydrateMatch($match);

        // Send notifications for teams added/removed
        $teamChanges = $this->notifierHelper->extractTeamChangesFromDiff($diff);
        foreach ($teamChanges['added'] as $addedTeamId) {
            // A team was added to this match
            $team = $this->teamMapper->findById($addedTeamId);
            $href = $this->notifierHelper->buildHrefForMatch($match, $team);
            $this->notifierHelper->postToParentsOfTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setHref($href)
                    ->setParam('match', $this->notifierHelper->buildMatchParam($match, $team))
                    ->setTitle(':child\'s team is now participating in :sport match :match'),
                $team
            );
        }
        foreach ($teamChanges['removed'] as $removedTeamId) {
            // A team was removed from this match
            $this->notifierHelper->postToParentsOfTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setHref('#')
                    ->setParam('match', new Param($match->getName()))
                    ->setTitle(':child\'s team is no longer participating in :sport match :match'),
                $this->teamMapper->findById($removedTeamId)
            );
        }

        if (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('start time', null, Param::STACKING_APPEND)),
                $match
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has been updated'),
                $match
            );
        }

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

    /**
     * @var Notifier\Helper;
     */
    protected $notifierHelper;

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
