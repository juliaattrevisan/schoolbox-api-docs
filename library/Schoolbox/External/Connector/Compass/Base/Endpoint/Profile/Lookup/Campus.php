<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\Compass\Base\Endpoint\IApi;

/**
 * This obtains a set of campuses for the friendlyOrgId in the suds config
 * This set is used as a hash lookup elsewhere during the imports since we
 * only need to obtain it once
 */
class Campus
{

    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var string
     */
    private string $organisationIdentifier;


    /**
     * @param IApi   $api
     * @param string $organisationIdentifier
     */
    public function __construct(IApi $api, string $organisationIdentifier)
    {
        $this->api = $api;
        $this->organisationIdentifier = $organisationIdentifier;
    }


    /**
     * Manufacture an (identifier, details) tuple for each of the campuses
     * in the given $organisationIdentifier
     */
    public function generate(): array
    {
        $campuses = $this->api->retrieveDocument('reference/v1/GetCampuses', [
            'organisationId' => $this->organisationIdentifier,
        ]);

        $stack = [];

        foreach ($campuses['d'] as $campus) {
            $stack[$campus['campusId']] = $campus;
        }

        return $stack;
    }
}