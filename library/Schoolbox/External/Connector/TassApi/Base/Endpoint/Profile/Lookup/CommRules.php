<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint\Profile\Lookup;

use Schoolbox\External\Connector\TassApi\Base\Endpoint\Codec\DSN;

/**
 * Preload all comm rules for all students everywhere
 */
class CommRules
{
    /**
     * A set of (product, api) tuples for the entire tass suite
     *
        $api = [
            Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_LMS_INTEGRATION),
            Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_STUDENT_DETAILS),
            Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT => new Endpoint\Api($host, $dsn, Endpoint\Codec\DSN::MODULE_IDENTITY_MANAGEMENT)
        ];
     *
     * @var array
     */
    private array $api;


    /**
     * Constructor.
     *
     * @param array $api
     */
    public function __construct(array $api)
    {
        $this->api = $api;
    }


    /**
     * Generate a set of (student external id, commrules) tuples
     *
     * @return array
     */
    public function generate(): array
    {
        $stack = [];

        $commRules = $this->api[DSN::MODULE_STUDENT_DETAILS]->retrieveDocument('getCommunicationRulesDetails', [
            'currentstatus' => 'current',
            'commtype' => 'all'
        ]);

        foreach ($commRules['commrules'] as $data) {
            $stack[$data['studcode']] = $data['addresses'];
        }

        return $stack;
    }
}
