<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\ItineraryItem;

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

/**
 * Notify parents/players of changes to a match's itinerary.
 *
 * @package Schoolbox\SportsFixtures\ItineraryItem
 */
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
        $notifyTime = clone $match->getStartAt();
        $notifyTime = $notifyTime->addHours(-24)->getUnixTimeStamp();

        switch ($method) {
            case Mapper::METHOD_CREATE:
            case Mapper::METHOD_UPDATE:
            case Mapper::METHOD_DELETE:
                $bundle->attach(self::TRIGGER_UPDATED, $notifyTime);
                break;
        }
    }

    /**
     * Setup upcoming notification when itinerary changes
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    public function notifyStudentUpdated(Model $itinerary, User $effector)
    {
        // Generic notification to team's participants that itinerary has updated
        $match = $this->notifierHelper->hydrateMatch($itinerary->getMatch());
        return $this->notifierHelper->postToTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_CREATED)
                ->setTitle('Your upcoming :sport match :match itinerary has been changed'),
            $match
        );
    }

    /**
     * Setup upcoming notification when itinerary changes
     *
     * @param Model $itinerary
     * @param User  $effector
     * @return Envelope
     */
    public function notifyParentUpdated(Model $itinerary, User $effector)
    {
        // Generic notification to team's participants that itinerary has updated
        $match = $this->notifierHelper->hydrateMatch($itinerary->getMatch());
        return $this->notifierHelper->postToParentsOfTeams(
            new Envelope(),
            $this->notifierHelper->buildBuilder($match, $effector, $this->getSlug(), self::TRIGGER_CREATED)
                 ->setTitle(':child\'s upcoming :sport match :match itinerary has been changed'),
            $match
        );
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
