<?php
/**
 * Notify parents/players of upcoming matches
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Result;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Team\Player\Model as TeamPlayerModel;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Division\Model as DivisionModel;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Sport\Model as SportModel;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\Match\Model as MatchModel;
use Schoolbox\SportsFixtures\Result\Mapper as ResultMapper;
use Schoolbox\SportsFixtures\Result\Model as ResultModel;
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
        if (empty($dependencies['resultMapper'])) {
            $dependencies['resultMapper'] = ResultMapper::getInstance();
        }
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
        return NSMapper::NS_SPORTS_FIXTURES_RESULT;
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

        switch ($method) {
            case Mapper::METHOD_CREATE:
                $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                break;
            case Mapper::METHOD_UPDATE:
                $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                break;
        }
    }

    /**
     * Setup upcoming match notification when new match created (Students)
     *
     * @param User $effector
     * @param ResultModel $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentCreated(ResultModel $result, User $effector, array $diff = null) {
        return $this->notifyStudents($result, $effector);
    }

    /**
     * Setup upcoming match notification when existing match updated (Students)
     *
     * @param User $effector
     * @param ResultModel $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(ResultModel $result, User $effector, array $diff = null) {
        return $this->notifyStudents($result, $effector);
    }

    /**
     * Setup upcoming match notification when new match created (Parents of players)
     *
     * @param User $effector
     * @param ResultModel $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentCreated(ResultModel $result, User $effector, array $diff = null) {
        return $this->notifyParents($result, $effector);
    }

    /**
     * Setup upcoming match notification when existing match updated (Parents of players)
     *
     * @param User $effector
     * @param ResultModel $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentUpdated(ResultModel $result, User $effector, array $diff = null) {
        return $this->notifyParents($result, $effector);
    }

    // Since update/create do pretty much the same thing, this is where the work actually happens

    /**
     * @param ResultModel $result
     * @param User $effector
     * @return Envelope
     */
    public function notifyParents(ResultModel $result, User $effector)
    {
        $envelope = new Envelope();

        /** @var ResultModel $result */
        $result = $this->resultMapper->findById($result->getId());
        /** @var MatchModel $match */
        $match = $this->matchMapper->findById($result->getMatch()->getId());
        /** @var DivisionModel $division */
        $division = $this->divisionMapper->findById($match->getDivision()->getId());
        /** @var SportModel $sport */
        $sport = $this->sportMapper->findById($division->getSport()->getId());

        $bob = new Builder();

        $ts = date("U");

        $bob->setTitle(':sport Match :match Results Updated')
            ->setParams([
                'sport' => new Param($sport->getName()),
                'match'  => new Param($match->getName(), "/sportsfixtures/match/{$match->getId()}/results/{$result->getId()}?{$ts}", Param::STACKING_REPLACE)
            ])
            ->setHref("/sportsfixtures/match/{$match->getId()}/results/{$result->getId()}")
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $match->getId(), self::TRIGGER_CREATED) ])
            ->setNamespace($this->getSlug())
        ;

        // Target home team parents

        $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $match->getHomeTeam()->getId());

        /** @var TeamPlayerModel $teamPlayer */
        foreach ($teamPlayers as $teamPlayer) {
            $envelope->targetToParent($teamPlayer->getUser()->getId(), $bob->build());
        }

        // Target away team parents

        $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $match->getAwayTeam()->getId());

        /** @var TeamPlayerModel $teamPlayer */
        foreach ($teamPlayers as $teamPlayer) {
            $envelope->targetToParent($teamPlayer->getUser()->getId(), $bob->build());
        }

        return $envelope;
    }

    /**
     * @param ResultModel $match
     * @param User $effector
     * @return Envelope
     */
    public function notifyStudents(ResultModel $result, User $effector)
    {
        $envelope = new Envelope();

        /** @var ResultModel $result */
        $result = $this->resultMapper->findById($result->getId());
        /** @var MatchModel $match */
        $match = $this->matchMapper->findById($result->getMatch()->getId());
        /** @var DivisionModel $division */
        $division = $this->divisionMapper->findById($match->getDivision()->getId());
        /** @var SportModel $sport */
        $sport = $this->sportMapper->findById($division->getSport()->getId());

        $bob = new Builder();

        $ts = date("U");

        $bob->setTitle(':sport Match :match Results Updated')
            ->setParams([
                'sport' => new Param($sport->getName(), $sport->getUrl()),
                'match'  => new Param($match->getName(), "/sportsfixtures/match/{$match->getId()}/results/{$result->getId()}?{$ts}", Param::STACKING_REPLACE)
            ])
            ->setHref("/sportsfixtures/match/{$match->getId()}/results/{$result->getId()}")
            ->setIcon("/portrait.php?userId={$effector->getExternalId()}&size=square64")
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $match->getId(), self::TRIGGER_CREATED) ])
            ->setNamespace($this->getSlug())
        ;

        // Target home team

        $this->log->info("ID: " . $match->getHomeTeam()->getId());
        $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $match->getHomeTeam()->getId());

        /** @var TeamPlayerModel $teamPlayer */
        foreach ($teamPlayers as $teamPlayer) {
            $envelope->targetToUser($teamPlayer->getUser()->getId(), $bob->build());
        }

        // Target away team

        $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $match->getAwayTeam()->getId());

        /** @var TeamPlayerModel $teamPlayer */
        foreach ($teamPlayers as $teamPlayer) {
            $envelope->targetToUser($teamPlayer->getUser()->getId(), $bob->build());
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
    
    /** @var ResultMapper $resultMapper */
    protected $resultMapper;

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
    protected function setResultMapper($mapper)
    {
        $this->resultMapper = $mapper;
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
