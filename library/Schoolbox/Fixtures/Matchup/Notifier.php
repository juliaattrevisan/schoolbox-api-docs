<?php

/**
 * Notify parents/players of upcoming matches
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Matchup;

use Alaress\Async\Notification\Parameter\Param;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Matchup\Model as Matchup;
use Schoolbox\Fixtures\Matchup\Notifier\Helper as NotifierHelper;
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
            MatchupMapper::getInstance(),
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

        /** @var Matchup $matchup */
        $matchup = $bundle->getEvent()->getObject();

        if ($matchup instanceof Matchup) {
            $startAt = $matchup->getStartAt()->getUnixTimeStamp();

            // Notify 24 hours before the match start time
            $notifyTime = clone $matchup->getStartAt();
            $notifyTime = $notifyTime->addHours(-24)->getUnixTimeStamp();

            switch ($method) {
                case Mapper::METHOD_CREATE:
                    // If the starting date is in past, we don't want to notify
                    if (!$matchup->getStartAt()->isPast()) {
                        $bundle->attach(self::TRIGGER_CREATED, $notifyTime);
                        $bundle->attach(self::TRIGGER_SNAPSHOT_CREATED, $startAt);
                    }
                    break;
                case Mapper::METHOD_UPDATE:
                    $diff = $bundle->getEvent()->getDiff();
                    // If there's no viewable change, we don't want to notify
                    if (count($diff) === 1 && array_key_exists('updatedAt', $diff)) {
                        break;
                    }

                    $now = new \DateTime();
                    $dayAhead = $now->modify('+ 1 day')->getTimestamp();
                    if ($matchup->getStartAt()->getUnixTimeStamp() < $dayAhead) {
                        // if the changed match is happening in 24hrs
                        $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                        $bundle->attach(self::TRIGGER_SNAPSHOT_UPDATED, $startAt);
                    } else if (array_key_exists('startAt', $diff)) {
                        // if the start time changed and the original start time was in 24hrs, notify
                        $startAtPrevious = \DateTime::createFromFormat('Y-m-d H:i:s', $diff['startAt'][1]);
                        if ($startAtPrevious->getTimestamp() < $dayAhead) {
                            $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                        }
                        // if the start time changed, update the upcoming notification time.
                        $bundle->detach(self::TRIGGER_CREATED);
                        $bundle->attach(self::TRIGGER_CREATED, $notifyTime);
                    } else if (
                        array_key_exists('itineraryStartAt', $diff)
                        && (0 < $diff['itineraryStartAt'][0] && $diff['itineraryStartAt'][0] < $dayAhead
                            || 0 < $diff['itineraryStartAt'][1] && $diff['itineraryStartAt'][1] < $dayAhead)
                    ) {
                        $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                    }
                    break;
            }
        } else {
            $this->log->warning("Got unknown type as object in fixtures event: " . get_class($matchup), ['char' => '+']);
        }
    }

    /**
     * Execute snapshot create
     *
     * @param Matchup $matchup
     * @param User $effector
     */
    public function notifySnapshotCreated(Matchup $matchup, User $effector)
    {
        $this->log->info("Creating snapshot for Match {$matchup->getId()} at {$matchup->getStartAt()->getDatabase()}", ['char' => '+']);

        $snapshot = new Snapshot($matchup);
        $snapshot->snapshot();
    }

    /**
     * Execute snapshot update
     *
     * @param Matchup $matchup
     * @param User $effector
     */
    public function notifySnapshotUpdated(Matchup $matchup, User $effector)
    {
        $this->log->info("Updating snapshot for Match {$matchup->getId()} at {$matchup->getStartAt()->getDatabase()}", ['char' => '+']);

        $snapshot = new Snapshot($matchup);
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
     * @param Matchup $matchup
     * @return Envelope
     */
    public function notifyStudentCreated(Matchup $matchup, User $effector)
    {
        $matchup = $this->notifierHelper->hydrateMatch($matchup);
        return $this->notifierHelper->postToTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_CREATED)
                ->setTitle('You have an upcoming :sport match :match'),
            $matchup
        );
    }

    /**
     * Setup upcoming match notification when existing match updated (Students)
     *
     * @param User       $effector
     * @param Matchup    $matchup
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(Matchup $matchup, User $effector, ?array $diff = null)
    {
        $envelope = new Envelope();
        $matchup  = $this->notifierHelper->hydrateMatch($matchup);

        // Send notifications for teams added/removed
        $teamChanges = $this->notifierHelper->extractTeamChangesFromDiff($diff);
        foreach ($teamChanges['added'] as $addedTeamId) {
            // A team was added to this match
            $team = $this->notifierHelper->findTeam($addedTeamId);
            $this->notifierHelper->postToTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setMatchHref($matchup, $team)
                    ->setMatchParam($matchup, $team)
                    ->setTitle('Your team is now participating in :sport match :match'),
                $team
            );
        }
        foreach ($teamChanges['removed'] as $removedTeamId) {
            // A team was removed from this match
            $this->notifierHelper->postToTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setHref('#')
                    ->setParam('match', new Param($matchup->getName()))
                    ->setTitle('Your team is no longer participating in :sport match :match'),
                $this->notifierHelper->findTeam($removedTeamId)
            );
        }

        if (isset($diff['venue']) && isset($diff['startAt'])) {
            // Both time and venue have changed
            // TODO: fix event stacking so that changing start and venue in one action stacks to "venue and start time"
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :time and :attribute')
                    ->setParam('time', new Param('start time', null, Param::STACKING_APPEND))
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $matchup
            );
        } elseif (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $matchup
            );
        } elseif (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has a new :attribute')
                    ->setParam('attribute', new Param('start time', null, Param::STACKING_APPEND)),
                $matchup
            );
        } elseif (isset($diff['itineraryStartAt'])) {
            // Itinerary has changed
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match itinerary has been changed'),
                $matchup
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->notifierHelper->postToTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Your upcoming :sport match :match has been updated'),
                $matchup
            );
        }

        return $envelope;
    }

    /**
     * Setup upcoming match notification when new match created (Parents of players)
     *
     * @param User $effector
     * @param Matchup $matchup
     * @return Envelope
     */
    public function notifyParentCreated(Matchup $matchup, User $effector)
    {
        $matchup = $this->notifierHelper->hydrateMatch($matchup);
        return $this->notifierHelper->postToParentsOfTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_CREATED)
                ->setTitle('Upcoming :sport match :match for :child'),
            $matchup
        );
    }

    /**
     * Setup upcoming match notification when existing match updated (Parents of players)
     *
     * @param User       $effector
     * @param Matchup    $matchup
     * @param array|null $diff
     * @return Envelope
     */
    public function notifyParentUpdated(Matchup $matchup, User $effector, ?array $diff = null)
    {
        $envelope = new Envelope();
        $matchup  = $this->notifierHelper->hydrateMatch($matchup);

        // Send notifications for teams added/removed
        $teamChanges = $this->notifierHelper->extractTeamChangesFromDiff($diff);
        foreach ($teamChanges['added'] as $addedTeamId) {
            // A team was added to this match
            $team = $this->notifierHelper->findTeam($addedTeamId);
            $this->notifierHelper->postToParentsOfTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setMatchHref($matchup, $team)
                    ->setMatchParam($matchup, $team)
                    ->setTitle(':child\'s team is now participating in :sport match :match'),
                $team
            );
        }
        foreach ($teamChanges['removed'] as $removedTeamId) {
            // A team was removed from this match
            $this->notifierHelper->postToParentsOfTeam(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setHref('#')
                    ->setParam('match', new Param($matchup->getName()))
                    ->setTitle(':child\'s team is no longer participating in :sport match :match'),
                $this->notifierHelper->findTeam($removedTeamId)
            );
        }

        if (isset($diff['venue']) && isset($diff['startAt'])) {
            // Both time and venue have changed
            // TODO: fix event stacking so that changing start and venue in one action stacks to "venue and start time"
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :time and :attribute')
                    ->setParam('time', new Param('start time', null, Param::STACKING_APPEND))
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $matchup
            );
        } elseif (isset($diff['venue'])) {
            // Venue has changed
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('venue', null, Param::STACKING_APPEND)),
                $matchup
            );
        } elseif (isset($diff['startAt'])) {
            // Time has changed
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has a new :attribute')
                    ->setParam('attribute', new Param('start time', null, Param::STACKING_APPEND)),
                $matchup
            );
        } elseif (isset($diff['itineraryStartAt'])) {
            // Itinerary has changed
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle(':child\'s upcoming :sport match :match itinerary has been changed'),
                $matchup
            );
        }

        if ($envelope->count() == 0 && count($diff)) {
            // Generic something has changed notification
            $envelope = $this->notifierHelper->postToParentsOfTeams(
                $envelope,
                $this->notifierHelper->buildBuilder($matchup, $effector, $this->getSlug(), self::TRIGGER_UPDATED)
                    ->setTitle('Upcoming :sport match :match for :child has been updated'),
                $matchup
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
