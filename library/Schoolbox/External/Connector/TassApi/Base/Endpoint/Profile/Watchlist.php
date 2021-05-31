<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile;

use Schoolbox\External\Connector\TassApi\Base\Endpoint;

/**
 * Class Watchlist
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile
 */
class Watchlist
{

    /**
     * Endpoint host
     *
     * @var string
     */
    private string $host;


    /**
     * Endpoint dsn
     *
     * @var string
     */
    private string $dsn;


    /**
     * Watchlist constructor.
     *
     * @param string $host
     * @param string $dsn
     */
    public function __construct(string $host, string $dsn)
    {
        $this->host = $host;
        $this->dsn = $dsn;
    }


    /**
     * Find a parent by their user_code, and also their contact details,
     * from the getParents() and getCommunicationRulesDeatils() endpoints
     *
     * Usage
     *
     * $watchlist = new Watchlist($config->get('schooldata_import_endpoint_host'), $config->get('schooldata_import_endpoint_key'));
     * $watchlist->contactDetails(array $user_code);
     *
     * @param array $watchlist
     * @return void
     * @throws \DebugBar\DebugBarException
     * @throws \DomainException
     * @throws \External\Tass\Api\Exception\InvalidApiKey
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function contactDetails(array $watchlist): void
    {
        $api = [
            Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION => new Endpoint\Api($this->host, $this->dsn, Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION),
            Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS => new Endpoint\Api($this->host, $this->dsn, Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS),
            Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT => new Endpoint\Api($this->host, $this->dsn, Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT)
        ];

        /* Find all the parent records in the watchlist
         */
        $parents = $api[Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT]->retrieveDocument('getParents', [
            'user_code' => 'all'
        ]);

        $family = [];

        foreach ($parents['parents'] as $row) {
            foreach ($row as $externalId => $parent) {
                if (in_array($externalId, $watchlist, false)) {
                    $family[] = [
                        $externalId => $parent
                    ];
                }
            }
        }

        /* Find all the comm records for parents int he watchlist
         */
        $commRules = $api[Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
            'currentstatus' => 'current',
            'commtype' => 'tkco'
        ]);

        $addresses = [];

        foreach ($commRules['commrules'] as $row) {
            foreach ($row['addresses'] as $address) {
                if (in_array($address['parent_code'], $watchlist, false)) {
                    $addresses[$address['parent_code']] = $address;
                }
            }
        }

        /* Toggle between raw payload data and filtered watchlist data
         */
//         new \Alaress_Probe($parents, $commRules);
        new \Alaress_Probe($family, $addresses);
    }
}
