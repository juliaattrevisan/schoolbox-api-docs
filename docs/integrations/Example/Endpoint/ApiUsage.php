<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this api
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
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
        /* Initialise counter if needed
         */
        if (!isset($this->usage[$endpoint])) {
            $this->usage[$endpoint] = 0;
        }

        /* Increment count of $endpoint usage
         */
        $this->usage[$endpoint]++;
    }


    /**
     * @return array
     */
    public function retrieve(): array
    {
        return $this->usage;
    }
}
