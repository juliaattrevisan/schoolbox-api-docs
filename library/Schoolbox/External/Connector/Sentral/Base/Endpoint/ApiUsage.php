<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this module
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class ApiUsage
{

    /**
     * A list of (endpoint, count) tuples
     *
     * @var array
     */
    private $usage = [];


    /**
     * Increment count
     *
     * @param string $endpoint
     */
    public function record(string $endpoint): void
    {
        /* Remove querystring params
         */
        $parts = explode('?', $endpoint);

        /* Replace int ids with wildcard representation
         */
        $baseEndpoint = preg_replace('#/\d+#', '/*', $parts[0]);

        /* Initialise counter if needed
         */
        if (!isset($this->usage[$baseEndpoint])) {
            $this->usage[$baseEndpoint] = 0;
        }

        /* Increment count of $endpoint usage
         */
        $this->usage[$baseEndpoint]++;
    }


    /**
     * @return array
     */
    public function retrieve(): array
    {
        return $this->usage;
    }
}
