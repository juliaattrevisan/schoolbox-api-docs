<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use RuntimeException;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
interface IApi
{

    /**
     * Retrieves a data document representing the result from the API call.
     *
     * @param string $method
     * @param array  $params
     * @return array|null
     * @throws RuntimeException
     */
    public function retrieveDocument(string $method, array $params): ?array;


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage;


    /**
     * @return array
     */
    public function getOptions(): array;
}
