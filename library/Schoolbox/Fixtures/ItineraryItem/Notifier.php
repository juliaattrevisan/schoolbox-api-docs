<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\ItineraryItem;

use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Notification\Envelope;
use Schoolbox\Realtime\Schedule\Module\AbstractModuleNotifier;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Player\Mapper as TeamPlayerMapper;
use Schoolbox\Fixtures\Division\Mapper as DivisionMapper;
use Schoolbox\Fixtures\Sport\Mapper as SportMapper;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Match\Model as MatchModel;
use Schoolbox\Fixtures\Match\Notifier\Helper as NotifierHelper;
use Schoolbox\User;

/**
 * Notify parents/players of changes to a match's itinerary.
 *
 * @package Schoolbox\Fixtures\ItineraryItem
 */
class Notifier extends AbstractModuleNotifier
{
    public const TRIGGER_CREATED = 'created';
    public const TRIGGER_UPDATED = 'updated';

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

        /** @var Model $itineraryItem */
        $itineraryItem = $bundle->getEvent()->getObject();

        // Notify 24 hours before the itinerary item's start time
        $notifyTime = clone $itineraryItem->getStartAt();
        $notifyTime->addHours(-24);
        $now = \Alaress_Date_Format::now();

        if ($now->between($notifyTime, $itineraryItem->getStartAt())) {
            switch ($method) {
                // Create will always notify
                case Mapper::METHOD_CREATE:
                    $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                    break;

                // Update only notifies if it's meaningfully different
                case Mapper::METHOD_UPDATE:
                    $bundle->attach(self::TRIGGER_UPDATED, self::IMMEDIATELY);
                    break;

                // Delete will always notify
                case Mapper::METHOD_DELETE:
                    // Use created trigger, because deleted doesn't actually exist
                    $bundle->attach(self::TRIGGER_CREATED, self::IMMEDIATELY);
                    break;
            }
        }
    }

    /**
     * Setup upcoming notification when itinerary is created
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    public function notifyStudentCreated(Model $itinerary, User $effector)
    {
        return $this->internalNotifyStudent($itinerary, $effector);
    }

    /**
     * Setup upcoming notification when itinerary changes
     *
     * This doesn't notify if the itinerary isn't meaningfully different to its
     * previous state.
     *
     * @param Model $itinerary
     * @param User  $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyStudentUpdated(Model $itinerary, User $effector, array $diff = null)
    {
        if (!$this->isMeaningfullyDifferent($diff)) {
            return new Envelope();
        }
        return $this->internalNotifyStudent($itinerary, $effector);
    }

    /**
     * Setup upcoming notification when itinerary is created
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    public function notifyParentCreated(Model $itinerary, User $effector)
    {
        return $this->internalNotifyParent($itinerary, $effector);
    }

    /**
     * Setup upcoming notification when itinerary changes
     *
     * This doesn't notify if the itinerary isn't meaningfully different to its
     * previous state.
     *
     * @param Model $itinerary
     * @param User  $effector
     * @param array $diff
     * @return Envelope
     */
    public function notifyParentUpdated(Model $itinerary, User $effector, array $diff = null)
    {
        if (!$this->isMeaningfullyDifferent($diff)) {
            return new Envelope();
        }
        return $this->internalNotifyParent($itinerary, $effector);
    }

    /**
     * Notify student when itinerary changes.
     *
     * This doesn't notify if the notify action was triggered by the creation
     * of a match; there's already a match notification for that.
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    private function internalNotifyStudent(Model $itinerary, User $effector): Envelope
    {
        // Generic notification to team's participants that itinerary has updated
        $match    = $this->notifierHelper->hydrateMatch($itinerary->getMatch());
        $envelope = new Envelope();
        if ($this->isNotifyTriggeredByMatchCreate($itinerary, $match)) {
            return $envelope;
        }

        return $this->notifierHelper->postToTeams(
            $envelope,
            $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_CREATED)
                                 ->setTitle('Your upcoming :sport match :match itinerary has been changed'),
            $match
        );
    }

    /**
     * Notify parents of students when itinerary changes.
     *
     * This doesn't notify if the notify action was triggered by the creation
     * of a match; there's already a match notification for that.
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    private function internalNotifyParent(Model $itinerary, User $effector): Envelope
    {
        // Generic notification to team's participants that itinerary has updated
        $match = $this->notifierHelper->hydrateMatch($itinerary->getMatch());
        $envelope = new Envelope();
        if ($this->isNotifyTriggeredByMatchCreate($itinerary, $match)) {
            return $envelope;
        }

        return $this->notifierHelper->postToParentsOfTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_CREATED)
                                 ->setTitle(':child\'s upcoming :sport match :match itinerary has been changed'),
            $match
        );
    }

    /**
     * Is the current state of an itinerary item meaningfully different to its
     * previous state?
     *
     * @param array|null $diff The difference between the two states
     * @return bool
     */
    private function isMeaningfullyDifferent(array $diff = null): bool
    {
        return !empty($diff['description']) || !empty($diff['startAt']);
    }

    /**
     * Is this notify action happening due to the initial create of the
     * itinerary item?
     *
     * @param Model      $itinerary
     * @param MatchModel $match
     * @return bool
     */
    private function isNotifyTriggeredByMatchCreate(Model $itinerary, MatchModel $match): bool
    {
        return $itinerary->getUpdatedAt()->eq($match->getCreatedAt());
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
