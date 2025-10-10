<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this module
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint
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
     * The base uri that consists of host and school route
     * Paginated data can appear separately in ApiUsage
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
     * Increment count and total duration spent in an endpoint
     *
     * @param string $endpoint
     * @param float $duration
     */
    public function record(string $endpoint, float $duration): void
    {
        /* Remove querystring params
         * Remove prefix
         */
        $baseEndpoint = str_replace($this->baseUri, '', explode('?', $endpoint)[0]);

        /* Initialise counter if needed
         */
        if (!isset($this->usage[$baseEndpoint])) {
            $this->usage[$baseEndpoint] = [
                'count' => 0,
                'duration' => 0
            ];
        }

        /* Increment count of $endpoint usage
         */
        $this->usage[$baseEndpoint]['count']++;
        $this->usage[$baseEndpoint]['duration'] += $duration;
    }


    /**
     * @return array
     */
    public function retrieve(): array
    {
        return $this->usage;
    }
}
