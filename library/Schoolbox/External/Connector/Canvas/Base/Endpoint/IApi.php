<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Canvas\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Canvas\Base\Endpoint
 */
interface IApi
{

    /**
     * Retrieves data from an endpoint
     * adding method / route as we can also use get / post / delete for the canvas api
     *
     * @param string $method
     * @param string $route
     * @param array  $params
     * @return array|null
     */
    public function retrieveDocument(string $method, string $route, array $params): ?array;
}
