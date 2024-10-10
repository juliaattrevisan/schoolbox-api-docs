<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint;

use GuzzleHttp\Client;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint
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


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage;


    /**
     * @return string
     */
    public function getHost(): string;


    /**
     * Get the HTTP client this API uses.
     *
     * @return Client
     */
    public function getClient(): Client;
}
