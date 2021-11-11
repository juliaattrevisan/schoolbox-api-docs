<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar\Lookup;

use Schoolbox\External\Connector\Compass\Base\Endpoint\Helper;
use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * Some Compass Timetable events don't have enrolments, such as yard duties and other
 * non-academic classes; these have attendees instead, and they're referenced by
 * compassPersonId not the external id.
 *
 * That means you gotta look up dudes separately, which is annoying
 */
class Staff
{

    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Helper
     */
    private Helper $helper;


    /**
     * Constructor.
     *
     * @param IApi  $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;

        /* Helper class
         */
        $this->helper = new Helper($this->api);
    }


    /**
     * Generate a list of guys indexed by their CompassPersonId
     */
    public function generate(string $lBound, string $uBound): array
    {
        $stack = [];

        $staffs = $this->api->retrieveDocument('person/v1/GetStaff', [
            'lBound' => $lBound,
            'uBound' => $uBound,
        ]);

        foreach ($staffs['d'] as $staff) {
            $stack[$staff['compassPersonId']] = [
                'externalId' => $this->findExternalId($staff['importIdentifiers']),
                'firstname' => $staff['firstName'],
                'lastname' => $staff['lastName'],
            ];
        }

        return $stack;
    }


    /**
     * A student has multiple externalIds based on whichever school they're in
     * These import identifiers are not the same if they're involved in multiple schools
     * We have to find the external id relevant to the current school (host)
     *
     * The IApi conveniently knows this, though
     *
     * @param array  $importIdentifiers
     * @return string|null
     */
    private function findExternalId(array $importIdentifiers): ?string
    {
        /* Example usage:
         *
         * $this->api->getHost()        https://wyndhamchristiancollege-integration-vic.compass.education/api/
         * $importIdentifier['school']  wyndhamchristiancollege-integration.vic.edu.au
         *
         * Commonality:                 wyndhamchristiancollege-integration
         */
        foreach ($importIdentifiers as $importIdentifier) {
            if ($this->helper->matchesHost($importIdentifier['school'])) {
                return $importIdentifier['importIdentifier'];
            }
        }

        return null;
    }
}