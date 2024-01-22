<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Lookup;

use \Schoolbox\External\Connector\Civica\Base\Endpoint\IApi;

class Alerts
{
    /**
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }


    /**
     * Generate a lookup of (externalId, (alertId, alert)) tuples
     * aggregated from all their campuses
     *
     * @return array
     */
    public function generate(): array
    {
        $stack = [];

        /* Filter out any dead alerts
         */
        $now = date('Y-m-d');

        $pages = $this->api->retrieve('GET', 'StudentAlerts/Jurisdiction');

        foreach ($pages as $page) {
            foreach ($page['values'] as $personAlerts) {
                foreach ($personAlerts['schools'] as $schoolAlerts) {
                    foreach ($schoolAlerts['alerts'] as &$alert) {
                        $alert['startDate'] = trim((string)$alert['startDate']);
                        $alert['endDate'] = trim((string)$alert['endDate']);

                        if ($this->isValid($now, $alert)) {
                            $stack[$personAlerts['studentPersonId']][$alert['alertId']] = $alert;
                        }
                    }
                }
            }
        }

        unset($alert);

        return $stack;
    }


    /**
     * Decide if we care about this $alert struct
     *
     * @param string $now
     * @param array $alert
     * @return bool
     */
    private function isValid(string $now, array $alert): bool
    {
        /* Start date is in the past
         * Look at the end date
         */
        if ($alert['startDate'] && $now >= $alert['startDate']) {
            if ('' === $alert['endDate']) {
                /* No end date specified;
                 * Alert is valid at the moment
                 */
                return true;
            }

            /* End date was specified, and it's in the future
             * Alert is valid at the moment
             */
            if ($now < $alert['endDate']) {
                return true;
            }
        }

        /* Alert starts in the future, or it ended in the past
         * Ignore it
         */
        return false;
    }
}
