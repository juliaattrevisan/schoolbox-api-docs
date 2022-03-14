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
     * The base uri that consists of host and school identifier
     * Paginated data can appear separately in ApiUsage
     *
     * The first page accesses an endpoint like 'students'
     * But paginated data goes to absolute url, 'http://api.wonde.com/v1.0/students'
     *
     * They appear as two separate line items under Api Endpoint Usage
     * unless we condense them together.
     *
     * @var string
     */
    private string $baseUri;


    /**
     * ApiUsage constructor.
     *
     * @param string $baseUri
     */
    public function __construct(string $baseUri)
    {
        $this->baseUri = $baseUri;
    }


    /**
     * Increment count
     *
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
