<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\Lookup;

use Schoolbox\External\Connector\Civica\Base\Endpoint\IApi;

class Caregivers
{
    /**
     * @param IApi $api
     */
    public function __construct(private IApi $api)
    {
    }


    /**
     * Generate a set of (externalId, contact data) tuples
     *
     * @return array
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function generate(): array
    {
        $stack = [];

        $pages = $this->api->retrieveForJurisdiction('GET', 'StudentCaregivers/Jurisdiction');

        foreach ($pages as $page) {
            foreach ($page['values'] as $caregivers) {
                $externalId = $caregivers['studentPersonId'];

                foreach ($caregivers['schools'] as $caregiverSchool) {
                    foreach ($caregiverSchool['caregivers'] as $caregiver) {
                        $stack[$externalId][] = $caregiver;
                    }
                }
            }
        }

        return $stack;
    }
}
