<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard;

/**
 * Interface IApi
 *
 * @package Schoolbox\Clipboard
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
}