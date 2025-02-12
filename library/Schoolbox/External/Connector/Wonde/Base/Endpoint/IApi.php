<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Wonde\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Wonde\Base\Endpoint
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


    /**
     * Optional settings in the wonde connector.
     *
     * @return array
     */
    public function getOptions(): array;
}
