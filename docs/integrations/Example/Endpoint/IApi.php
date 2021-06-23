<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Example\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Example\Base\Endpoint
 */
interface IApi
{

    /**
     * Retrieves data from an endpoint
     *
     * @param string $method
     * @param array  $params
     * @return array|null
     */
    public function retrieveDocument(string $method, array $params): ?array;
}
