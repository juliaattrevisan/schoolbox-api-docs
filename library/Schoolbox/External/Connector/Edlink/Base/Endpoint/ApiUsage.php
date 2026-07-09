<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this module
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
class ApiUsage
{
    /**
     * A list of (endpoint, count, duration) tuples
     *
     * @var array
     */
    private array $usage = [];

    /**
     * ApiUsage constructor.
     */
    public function __construct(private string $baseUri)
    {
    }

    /**
     * Increment count and total duration spent in an endpoint
     */
    public function record(string $endpoint, float $duration): void
    {
        /* Remove querystring params and base-uri prefix so that paginated
         * calls for the same endpoint collapse into a single row.
         */
        $baseEndpoint = str_replace($this->baseUri, '', explode('?', $endpoint)[0]);

        if (!isset($this->usage[$baseEndpoint])) {
            $this->usage[$baseEndpoint] = [
                'count' => 0,
                'duration' => 0.0
            ];
        }

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
