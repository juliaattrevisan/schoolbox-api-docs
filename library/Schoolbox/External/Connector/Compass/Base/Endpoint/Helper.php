<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

/**
 * Assorted functions that are useful across the spectrum of Compass
 */
class Helper
{

    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @param IApi $api
     */
    public function __construct(IApi $api)
    {
        $this->api = $api;
    }


    /**
     * Find the organisation identifier
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getOrganisationIdentifier(): string
    {
        $orgs = $this->api->retrieveDocument('reference/v1/GetOrganisations', []);

        foreach ($orgs['d'] as $org) {
            if ($this->matchesHost($org['friendlyOrgId'])) {
                return $org['orgId'];
            }
        }

        throw new \InvalidArgumentException('Unknown organisation id for the api host specified.');
    }


    /**
     * Decide if $organisation matches the endpoint host in suds
     * This is how we identify records shared across multiple schools
     *
     * @param string $organisationName
     * @return bool
     */
    public function matchesHost(string $organisationName): bool
    {
        $school = explode('.', $organisationName)[0];

        return strpos($this->api->getHost(), $school) > -1;
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
    public function findExternalId(array $importIdentifiers): ?string
    {
        /* Example usage:
         *
         * $this->api->getHost()        https://wyndhamchristiancollege-integration-vic.compass.education/api/
         * $importIdentifier['school']  wyndhamchristiancollege-integration.vic.edu.au
         *
         * Commonality:                 wyndhamchristiancollege-integration
         */
        foreach ($importIdentifiers as $importIdentifier) {
            if ($this->matchesHost($importIdentifier['school'])) {
                return $importIdentifier['importIdentifier'];
            }
        }

        return null;
    }
}
