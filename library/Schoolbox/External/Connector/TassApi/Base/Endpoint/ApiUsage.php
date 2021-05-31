<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

/**
 * Class ApiUsage
 *
 * Record a tally of endpoint accesses in this module
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class ApiUsage
{

    /**
     * One of studentdetails, identitymanagement, lmsintegration
     *
     * @var string
     */
    private string $module;


    /**
     * A list of (module, endpoint, count) tuples
     *
     * @var array
     */
    private array $usage = [];


    /**
     * ApiUsage constructor.
     *
     * @param string $module
     */
    public function __construct(string $module)
    {
        $this->module = $module;
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
        if (!isset($this->usage[$this->module][$endpoint])) {
            $this->usage[$this->module][$endpoint] = 0;
        }

        /* Increment count of $endpoint usage in $module
         */
        $this->usage[$this->module][$endpoint]++;
    }


    /**
     * @return array
     */
    public function retrieve(): array
    {
        return $this->usage;
    }
}
