<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard;

use Alaress\Database\Connector\IConnector;
use Calendar\Api\ModuleChecker;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\User;

/**
 * Class ApiFactory
 *
 * A unique implementation of Calendar_Api_Factory
 *
 * Finds which subscriptions are enabled
 */
class ApiFactory
{
    /**
     * Constructor
     */
    public function __construct(private \Calendar_Api_Factory $factory)
    {
    }


    /**
     * Get a list of all the calendar subscriptions that were enabled
     * Pass-through to the existing Factory which can already do this
     *
     * @return array
     */
    public function generateAvailableSubscriptions(): array
    {
        return $this->factory->generateAvailableSubscriptions();
    }


    /**
     * Generate a composite calendar api, whose $subscription data outputs are
     * restricted to only show records that have one or more of the event types
     * specified by the $eventTypes list (a list of primary keys in luEventType)
     *
     * Some calendar subscriptions have an Event Type dropdown, which you can
     * see in https://vagrant.dev.schoolbox.cloud/adminv2/calendar/Subscribe
     *
     * These allow events from a subscription to masquerade as a user-defined
     * event type. These user-defined event types should be respected, if extant
     *
     * @param User  $viewee
     * @param User  $viewer
     * @param array $chosenSubscriptions
     * @return \Calendar_Api_EventType_Interface
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produceFilteredApi(
        User $viewee,
        User $viewer,
        array $chosenSubscriptions
    ): \Calendar_Api_EventType_Interface {

        /* Get a list of instance-enabled subscriptions; these are sources of data
         * and are activated/deactivated here:
         *
         * https://vagrant.dev.schoolbox.cloud/adminv2/calendar/Subscribe
         */
        $subscriptions = $this->generateAvailableSubscriptions();

        /* Filter that list of enabled subscriptions with the ones that were
         * enabled via the component configuration checkboxes; this creates
         * a subset of what could be made available
         *
         * Mine out the classes that spawn events from connector config and pile
         * them into a set of (connector id, subscription classname) tuples
         */
        $composite = new \Calendar_Api_Composite();

        /* Note: you can't delete subscriptions
         *
         * Special Case: If you haven't chosen any subscriptions, enable all subscriptions
         * and just add all the ones that are available. This is a very liberal initial state.
         * Otherwise, just add the ones that were selected in $chosenSubscriptions
         */
        foreach ($subscriptions as $subscription) {
            $numChosenSubscriptions = \count($chosenSubscriptions);

            if (!$numChosenSubscriptions || \in_array($subscription->getId(), $chosenSubscriptions, true)) {
                $composite->add($this->factory->produceFromConfig($subscription, $viewee, $viewer));
            }
        }

        /* For each subscription still surviving, have it generate its events
         * but only retrieve events that possessed at least one of the luEventTypes
         * specified in the $eventTypes stack
         *
         * This is done via the Composite's call to ->getEvents($this->startTimestamp, $this->endTimestamp, $allowedEventTypes);
         */
        return $composite;

        /* This produces the intersection of eventTypes x subscriptions such that
         * the events produces equate to: "out of the enabled subscriptions specified
         * by the component config, give me all the events that have an luEventType
         * listed in $eventTypes"
         *
         * It also minimises the data set prior to sending to vue frontend,
         * reduces load on server to generate the data (could be a billion events)
         * and is generally faster since there are multiple calendar components now
         */
    }
}
