<?php
/**
 * Notify parents/players of upcoming matches
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Fixtures\Match;

use Alaress\Async\Notification\Parameter\Param;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Match\Model as Match;
use Schoolbox\Fixtures\Match\Notifier\Helper as NotifierHelper;
use Schoolbox\Fixtures\Snapshot\Snapshot;
use Schoolbox\User;

class Notifier extends AbstractModuleNotifier
{
    const TRIGGER_CREATED = 'created';
    const TRIGGER_UPDATED = 'updated';
    const TRIGGER_SNAPSHOT_CREATED = 'snapshotcreated';
    const TRIGGER_SNAPSHOT_UPDATED = 'snapshotupdated';

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

        /** @var Match $match */
        $match = $bundle->getEvent()->getObject();

        if($match instanceof Match) {
            $startAt = $match->getStartAt()->getUnixTimeStamp();

            // Notify 24 hours before the match start time
            $notifyTime = clone $match->getStartAt();
            $notifyTime = $notifyTime->addHours(-24)->getUnixTimeStamp();

            switch ($method) {
                case Mapper::METHOD_CREATE:
                    $bundle->attach(self::TRIGGER_CREATED, $notifyTime);
                    $bundle->attach(self::TRIGGER_SNAPSHOT_CREATED, $startAt);
                    break;
                case Mapper::METHOD_UPDATE:
                    $bundle->attach(self::TRIGGER_UPDATED, $notifyTime);
                    $bundle->attach(self::TRIGGER_SNAPSHOT_UPDATED, $startAt);
                    break;
            }
        }
        else {
            $this->log->warning("Got unknown type as object in fixtures event: ".get_class($match), ['char' => '+']);

        }
    }

    /**
     * Execute snapshot create
     *
     * @param Match $match
     * @param User $effector
     */
    public function notifySnapshotCreated(Match $match, User $effector)
    {
        $this->log->info("Creating snapshot for Match {$match->getId()} at {$match->getStartAt()->getDatabase()}", ['char' => '+']);

        $snapshot = new Snapshot($match);
        $snapshot->snapshot();
    }

    /**
     * Execute snapshot update
     *
     * @param Match $match
     * @param User $effector
     */
    public function notifySnapshotUpdated(Match $match, User $effector)
    {
        $this->log->info("Updating snapshot for Match {$match->getId()} at {$match->getStartAt()->getDatabase()}", ['char' => '+']);

        $snapshot = new Snapshot($match);
        $snapshot->snapshot();
    }

    /**
     * Custom callbacks that break away from the established pattern
     *
     * @param string $label
     * @return array
     * @throws \Schoolbox\Realtime\Schedule\ScheduleException
     */
    public function retrieveCallbacks($label)
    {
        $callbacks = parent::retrieveCallbacks($label);
        $method    = "notify{$label}";

        if (method_exists($this, $method)) {
            $callbacks[] = array($this, $method);
        }

        return $callbacks;
    }

    /**
     * Setup upcoming match notification when new match created (Students)
     *
     * @param User $effector
     * @param Match $match
     * @return Envelope
     */
    public function notifyStudentCreated(Match $match, User $effector)
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
     * @param Match $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(Match $match, User $effector, array $diff = null)
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

        if (isset($diff['venue']) && isset($diff['startAt'])) {
            // Both time and venue have changed
            // TODO: fix event stacking so that changing start and venue in one action stacks to "venue and start time"
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :time and :attribute')
                    ->setParam('time', new Param('start time', null, Param::STACKING_APPEND))
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        } elseif (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        } elseif (isset($diff['startAt'])) {
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
     * @param Match $match
     * @return Envelope
     */
    public function notifyParentCreated(Match $match, User $effector)
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
     * @param Match $match
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentUpdated(Match $match, User $effector, array $diff = null)
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

        if (isset($diff['venue']) && isset($diff['startAt'])) {
            // Both time and venue have changed
            // TODO: fix event stacking so that changing start and venue in one action stacks to "venue and start time"
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :time and :attribute')
                    ->setParam('time', new Param('start time', null, Param::STACKING_APPEND))
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        } elseif (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $match
            );
        } elseif (isset($diff['startAt'])) {
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
