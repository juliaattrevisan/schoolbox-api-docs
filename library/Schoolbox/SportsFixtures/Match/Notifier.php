<?php
/**
 * Notify parents/players of upcoming matches
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Match;

use Alaress\Async\Notification\Parameter\Param;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\SportsFixtures\Division\Mapper as DivisionMapper;
use Schoolbox\SportsFixtures\Sport\Mapper as SportMapper;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\Match\Model as MatchModel;
use Schoolbox\SportsFixtures\Match\Notifier\Helper as NotifierHelper;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';

    /**
     * @var NotifierHelper
     */
    protected $notifierHelper;

    /**
     * @param null $dependencies
     */
    public function loadDependencies($dependencies = null)
    {
        $dependencies['notifierHelper'] = new NotifierHelper(
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
            $team = $this->notifierHelper->findTeam($addedTeamId);
            $this->notifierHelper->postToTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setMatchHref($match, $team)
                    ->setMatchParam($match, $team)
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
                $this->notifierHelper->findTeam($removedTeamId)
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
            $team = $this->notifierHelper->findTeam($addedTeamId);
            $this->notifierHelper->postToParentsOfTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setMatchHref($match, $team)
                    ->setMatchParam($match, $team)
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
                $this->notifierHelper->findTeam($removedTeamId)
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
     * @param NotifierHelper $notifierHelper
     * @return $this
     */
    public function setNotifierHelper($notifierHelper)
    {
        $this->notifierHelper = $notifierHelper;
        return $this;
    }
}
