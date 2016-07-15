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
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\SportsFixtures\Team\User\Mapper as TeamUserMapper;
use Schoolbox\SportsFixtures\Team\User\Model as TeamUserModel;
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
        if (empty($dependencies['teamUserMapper'])) {
            $dependencies['teamUserMapper'] = TeamUserMapper::getInstance();
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
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentCreated(MatchModel $match, User $effector, array $diff = null) {
        return $this->notifyStudents($match, $effector);
    }

    /**
     * Setup upcoming match notification when existing match updated (Students)
     *
     * @param User $effector
     * @param MatchModel $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(MatchModel $match, User $effector, array $diff = null) {
        return $this->notifyStudents($match, $effector);
    }

    /**
     * Setup upcoming match notification when new match created (Parents of players)
     *
     * @param User $effector
     * @param MatchModel $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentCreated(MatchModel $match, User $effector, array $diff = null) {
        return $this->notifyParents($match, $effector);
    }

    /**
     * Setup upcoming match notification when existing match updated (Parents of players)
     *
     * @param User $effector
     * @param MatchModel $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentUpdated(MatchModel $match, User $effector, array $diff = null) {
        return $this->notifyParents($match, $effector);
    }

    // Since update/create do pretty much the same thing, this is where the work actually happens
    
    /**
     * @param Model $match
     * @param User $effector
     * @return Envelope
     */
    public function notifyParents(MatchModel $match, User $effector)
    {
        $envelope = new Envelope();

        $match = $this->matchMapper->findById($match->getId());
        $division = $this->divisionMapper->findById($match->getDivision()->getId());
        $sport = $this->sportMapper->findById($division->getSport()->getId());

        $bob = new Builder();

        $ts = date("U");

        $bob->setTitle('Upcoming :sport Match :match')
            ->setParams([
                'sport' => new Param($sport->getName()),
                'match'  => new Param($match->getName())
            ])
            ->setHref("/sportsfixtures/match/{$match->getId()}")
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $match->getId(), self::TRIGGER_CREATED) ])
            ->setNamespace($this->getSlug())
        ;

        // Target home team parents

        $teamUsers = $this->teamUserMapper->findByColumn("team_id", $match->getHomeTeam()->getId());

        /** @var TeamUserModel $teamUser */
        foreach ($teamUsers as $teamUser) {
            $envelope->targetToParent($teamUser->getUser()->getId(), $bob->build());
        }

        // Target away team parents

        $teamUsers = $this->teamUserMapper->findByColumn("team_id", $match->getAwayTeam()->getId());

        /** @var TeamUserModel $teamUser */
        foreach ($teamUsers as $teamUser) {
            $envelope->targetToParent($teamUser->getUser()->getId(), $bob->build());
        }

        return $envelope;
    }

    /**
     * @param Model $match
     * @param User $effector
     * @return Envelope
     */
    public function notifyStudents(MatchModel $match, User $effector)
    {
        $envelope = new Envelope();

        $match = $this->matchMapper->findById($match->getId());
        $division = $this->divisionMapper->findById($match->getDivision()->getId());
        $sport = $this->sportMapper->findById($division->getSport()->getId());

        $bob = new Builder();

        $ts = date("U");

        $bob->setTitle('Upcoming :sport Match :match')
            ->setParams([
                'sport' => new Param($sport->getName(), $sport->getUrl()),
                'match'  => new Param($match->getName(), "/sportsfixtures/match/{$match->getId()}?{$ts}", Param::STACKING_REPLACE)
            ])
            ->setHref("/sportsfixtures/match/{$match->getId()}")
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $match->getId(), self::TRIGGER_CREATED) ])
            ->setNamespace($this->getSlug())
        ;

        // Target home team

        $this->log->info("ID: " . $match->getHomeTeam()->getId());
        $teamUsers = $this->teamUserMapper->findByColumn("team_id", $match->getHomeTeam()->getId());

        /** @var TeamUserModel $teamUser */
        foreach ($teamUsers as $teamUser) {
            $envelope->targetToUser($teamUser->getUser()->getId(), $bob->build());
        }

        // Target away team

        $teamUsers = $this->teamUserMapper->findByColumn("team_id", $match->getAwayTeam()->getId());

        /** @var TeamUserModel $teamUser */
        foreach ($teamUsers as $teamUser) {
            $envelope->targetToUser($teamUser->getUser()->getId(), $bob->build());
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

    /** @var MatchMapper $matchMapper */
    protected $matchMapper;

    /** @var DivisionMapper $divisionMapper */
    protected $divisionMapper;

    /** @var SportMapper $sportMapper */
    protected $sportMapper;

    /** @var TeamUserMapper $teamUserMapper */
    protected $teamUserMapper;

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
    protected function setTeamUserMapper($mapper)
    {
        $this->teamUserMapper = $mapper;
    }
}
