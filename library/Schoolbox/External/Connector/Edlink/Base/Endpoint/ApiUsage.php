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
     * @var array
     */
    private array $usage = [];

    /**
     * @var string
     */
    private string $baseUri;

    /**
     * @param string $baseUri
     */
    public function __construct(string $baseUri)
    {
        $this->baseUri = $baseUri;
    }

    /**
     * @param string $endpoint
     */
    public function record(string $endpoint): void
    {
        $baseEndpoint = str_replace($this->baseUri, '', explode('?', $endpoint)[0]);

        if (!isset($this->usage[$baseEndpoint])) {
            $this->usage[$baseEndpoint] = 0;
        }

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
