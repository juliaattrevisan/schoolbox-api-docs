<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this module
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint
 */
class ApiUsage
{

    /**
     * A list of (module, endpoint, count) tuples
     *
     * @var array
     */
    private array $usage = [];


    /**
     * ApiUsage constructor.
     *
     */
    public function __construct()
    {
    }


    /**
     * Increment count
     *
     * @param string $endpoint
     */
    public function record(string $endpoint): void
    {
        /* Remove querystring params
         */
        $baseEndpoint = explode('?', $endpoint)[0];

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
