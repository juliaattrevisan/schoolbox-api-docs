<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

/**
 * Canvas Importer API Interface
 */
interface IApi
{

    /**
     * Access $route (an api endpoint) using $method (get, post, etc)
     *
     * @param string $method
     * @param string $route
     * @param array  $queryArgs
     * @return array
     */
    public function retrieve(string $method, string $route, array $queryArgs): array;
}
