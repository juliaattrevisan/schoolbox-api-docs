<?php

/**
 * Notify parents/players of upcoming matches
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Result;

use Alaress\Async\Notification\Instance\Builder;
use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Notification\Parameter\UserParam;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Team\Player\Model as TeamPlayer;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Division\Model as Division;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Sport\Model as Sport;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Result\Mapper as ResultMapper;
use Schoolbox\Fixtures\Result\Model as Result;
use Schoolbox\User;
use Monolog\Logger;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'resultCreated';
    const TRIGGER_UPDATED = 'resultUpdated';

    /**
     * @var MatchupMapper $matchupMapper
     */
    protected $matchupMapper;

    /**
     * @var ResultMapper $resultMapper
     */
    protected $resultMapper;

    /**
     * @var DivisionMapper $divisionMapper
     */
    protected $divisionMapper;

    /**
     * @var SportMapper $sportMapper
     */
    protected $sportMapper;

    /**
     * @var TeamPlayerMapper $teamPlayerMapper
     */
    protected $teamPlayerMapper;

    /**
     * @param mixed $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        if (empty($dependencies['resultMapper'])) {
            $dependencies['resultMapper'] = ResultMapper::getInstance();
        }
        if (empty($dependencies['matchupMapper'])) {
            $dependencies['matchupMapper'] = MatchupMapper::getInstance();
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
     * @param User       $effector
     * @param Result     $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentResultcreated(Result $result, User $effector, array $diff = null)
    {
        return $this->notifyStudents($result, $effector);
    }

    /**
     * Setup upcoming match notification when existing match updated (Students)
     *
     * @param User       $effector
     * @param Result     $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentResultupdated(Result $result, User $effector, array $diff = null)
    {
        return $this->notifyStudents($result, $effector);
    }

    /**
     * Setup upcoming match notification when new match created (Parents of players)
     *
     * @param User       $effector
     * @param Result     $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentResultcreated(Result $result, User $effector, array $diff = null)
    {
        return $this->notifyParents($result, $effector);
    }

    /**
     * Setup upcoming match notification when existing match updated (Parents of players)
     *
     * @param User       $effector
     * @param Result     $result
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentResultupdated(Result $result, User $effector, array $diff = null)
    {
        return $this->notifyParents($result, $effector);
    }

    // Since update/create do pretty much the same thing, this is where the work actually happens

    /**
     * @param Result $result
     * @param User $effector
     * @return Envelope
     */
    public function notifyParents(Result $result, User $effector)
    {
        $envelope = new Envelope();

        /** @var Result $result */
        $result = $this->resultMapper->findById($result->getId());
        /** @var Matchup $matchup */
        $matchup = $this->matchupMapper->findById($result->getMatchup()->getId());
        /** @var Division $division */
        $division = $this->divisionMapper->findById($matchup->getDivision()->getId());
        /** @var Sport $sport */
        $sport = $this->sportMapper->findById($division->getSport()->getId());

        $bob = new Builder();
        $ts  = \Alaress_Date_Format::now()->getUnixTimeStamp();
        $bob->setTitle('Results have been updated for :child\'s :sport match :match')
            ->setIcon($this->getUserIcon($effector))
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $matchup->getId(), self::TRIGGER_CREATED) ])
            ->setNamespace($this->getSlug())
        ;

        //Target parents of all teams
        foreach ($matchup->getAllTeams() as $team) {
            $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $team->getId());

            /** @var TeamPlayer $teamPlayer */
            foreach ($teamPlayers as $teamPlayer) {
                $href = "/fixtures/matches/{$matchup->getId()}/results/{$team->getId()}";
                $bob->setHref($href)
                    ->setParams([
                                    'child' => new UserParam($teamPlayer->getUser()),
                                    'sport' => new Param($sport->getName()),
                                    'match' => new Param($matchup->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE)
                                ])
                    ->putMeta('studentExternalId', $teamPlayer->getUser()->getExternalId());
                $envelope->targetToParent($teamPlayer->getUser()->getId(), $bob->build());
            }
        }

        return $envelope;
    }

    /**
     * @param Result $result
     * @param User $effector
     * @return Envelope
     */
    public function notifyStudents(Result $result, User $effector)
    {
        $envelope = new Envelope();

        /** @var Result $result */
        $result = $this->resultMapper->findById($result->getId());
        /** @var Matchup $matchup */
        $matchup = $this->matchupMapper->findById($result->getMatchup()->getId());
        /** @var Division $division */
        $division = $this->divisionMapper->findById($matchup->getDivision()->getId());
        /** @var Sport $sport */
        $sport = $this->sportMapper->findById($division->getSport()->getId());

        $bob = new Builder();
        $ts  = \Alaress_Date_Format::now()->getUnixTimeStamp();
        $bob->setTitle('Results have been updated for your :sport match :match')
            ->setIcon($this->getUserIcon($effector))
            ->setSeverity(Logger::INFO)
            ->setTopics([ sprintf('%s.%d.%s', $this->getSlug(), $matchup->getId(), self::TRIGGER_CREATED) ])
            ->setNamespace($this->getSlug())
        ;

        // Target players of all teams
        foreach ($matchup->getAllTeams() as $team) {
            $this->log->info("ID: " . $team->getId());
            $teamPlayers = $this->teamPlayerMapper->findByColumn("team_id", $team->getId());

            /** @var TeamPlayer $teamPlayer */
            foreach ($teamPlayers as $teamPlayer) {
                $href = "/fixtures/matches/{$matchup->getId()}/results/{$team->getId()}";
                $bob->setHref($href)
                    ->setParams([
                                    'sport' => new Param($sport->getName()),
                                    'match' => new Param($matchup->getName(), "{$href}?{$ts}", Param::STACKING_REPLACE)
                                ]);
                $envelope->targetToUser($teamPlayer->getUser()->getId(), $bob->build());
            }
        }

        return $envelope;
    }

    /* These methods are dynamically accessed via AbstractModuleNotifier::loadDependencies
     */

    /**
     * @param MatchupMapper $mapper
     */
    protected function setMatchupMapper($mapper)
    {
        $this->matchupMapper = $mapper;
    }

    /**
     * @param ResultMapper $mapper
     */
    protected function setResultMapper($mapper)
    {
        $this->resultMapper = $mapper;
    }

    /**
     * @param DivisionMapper $mapper
     */
    protected function setDivisionMapper($mapper)
    {
        $this->divisionMapper = $mapper;
    }

    /**
     * @param SportMapper $mapper
     */
    protected function setSportMapper($mapper)
    {
        $this->sportMapper = $mapper;
    }

    /**
     * @param TeamPlayerMapper $mapper
     */
    protected function setTeamPlayerMapper($mapper)
    {
        $this->teamPlayerMapper = $mapper;
    }
}
