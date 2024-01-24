<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Civica\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Civica\Base\Endpoint\IsValid;

class Alerts
{
    /**
     * @param IsValid $isValid
     * @param IApi $api
     */
    public function __construct(private IsValid $isValid, private IApi $api)
    {
    }


    /**
     * Generate a lookup of (externalId, (alertId, alert)) tuples
     * aggregated from all their campuses
     *
     * @return array
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function generate(): array
    {
        $stack = [];

        $pages = $this->api->retrieveForJurisdiction('GET', 'StudentAlerts/Jurisdiction');

        foreach ($pages as $page) {
            foreach ($page['values'] as $personAlerts) {
                foreach ($personAlerts['schools'] as $schoolAlerts) {
                    foreach ($schoolAlerts['alerts'] as &$alert) {
                        $alert['startDate'] = trim((string)$alert['startDate']);
                        $alert['endDate'] = trim((string)$alert['endDate']);

                        /* Use the dates to figure out if this is relevant or not
                         */
                        if ($this->isValid->isSatisifiedBy($alert)) {
                            $externalId = (string)$personAlerts['studentPersonId'];

                            $stack[$externalId][$alert['alertId']] = $alert;
                        }
                    }
                }
            }
        }

        unset($alert);

        return $stack;
    }
}
