<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
interface IApi
{
    /**
     * Retrieves data from an endpoint
     *
     * @param string $method
     * @param string $endpoint
     * @param string $scope
     * @param array  $params
     * @return array
     */
    public function retrieve(string $method, string $endpoint, string $scope, array $params = []): array;


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage;
}
