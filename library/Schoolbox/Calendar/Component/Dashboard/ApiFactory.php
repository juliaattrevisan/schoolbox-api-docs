<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard;

use Calendar\Api\ModuleChecker;

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
     * A list of all the possible event types to display
     *
     * @var array|null
     */
    private ?array $allTypes = null;


    /**
     * @var ModuleChecker
     */
    private ModuleChecker $moduleChecker;


    /**
     * Constructor
     */
    public function __construct(ModuleChecker $moduleChecker)
    {
        $this->moduleChecker = $moduleChecker;
    }


    /**
     * Get a list of all the calendar subscriptions that were enabled
     *
     * @return array
     */
    public function generateAvailableSubscriptions(): array
    {
        /* Only compute this once
         */
        if (null === $this->allTypes) {
            $this->allTypes = [];

            $allConfigRecords = \Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

            /* These configs are base64-encode()'d to get around a weird issue with unserialize
             * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
             */
            foreach ($allConfigRecords as $currentConfig) {
                $config = $currentConfig->decodeConfig();

                try {
                    /* Also check if the module was enabled as part of a Plan
                     * Some modules won't be available in the current schoolbox Plan
                     */
                    if ($this->moduleChecker->isEnabled($config)) {
                        /* Update $config so it knows the calendar_connector id from whence it came
                         * This is used in produce() and passed to each unique/default implementation
                         */
                        $config->setId($currentConfig->getId());

                        $this->allTypes[] = $config;
                    }
                } catch (\TypeError $e) {
                    // I will not buy this record; it is scratched
                }
            }
        }

        return $this->allTypes;
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
     * @param array $eventTypes
     * @return void
     */
    public function generateFilteredApi(array $subscriptions, array $eventTypes)
    {

        /* Get a list of instance-enabled subscriptions; these are sources of data
         * and are activated/deactivated here:
         *
         * https://vagrant.dev.schoolbox.cloud/adminv2/calendar/Subscribe
         */

        /* Filter that list of enabled subscriptions with the ones that were
         * enabled via the component configuration checkboxes; this creates
         * a subset of what could be made available
         */

        /* For each subscription still surviving, have it generate its events
         * but only retrieve events that possessed at least one of the luEventTypes
         * specified in the $eventTypes stack
         */

        /* This produces the intersection of eventTypes x subscriptions such that
         * the events produces equate to: "out of the enabled subscriptions specifeid
         * by the component config, give me all the events that have an luEventType
         * listed in $eventTypes"
         *
         * It also minimises the data set prior to sending to vue frontend,
         * reduces load on server to generate the data (could be a billion events)
         * and is generally faster since there are multiple calendar components now
         */

        foreach ($subscriptions as $subscription) {
        }
    }
}