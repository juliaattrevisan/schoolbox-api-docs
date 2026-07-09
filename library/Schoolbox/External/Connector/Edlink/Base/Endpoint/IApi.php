<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint
 */
interface IApi
{
    /**
     * Retrieves data from an endpoint
     *
     * @param string $method
     * @param string $endpoint
     * @param array  $params
     * @return array
     */
    public function retrieve(string $method, string $endpoint, array $params = []): array;

    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage;
}
