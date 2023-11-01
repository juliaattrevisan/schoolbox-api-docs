<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this api
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint
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
     * @param string $baseUri The base uri that consists of host and school route.
     *                        Paginated data can appear separately in ApiUsage
     */
    public function __construct(private string $baseUri)
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
         * Remove prefix
         */
        $baseEndpoint = str_replace($this->baseUri, '', explode('?', $endpoint)[0]);

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
