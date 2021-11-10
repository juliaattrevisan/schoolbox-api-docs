<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Redis\Cache;

/**
 * Interface for cache key generators.
 */
interface IKeyGenerator
{
    /**
     * Generates a Redis key from a cache item's key.
     *
     * @param string $key
     * @return string
     */
    public function generateKey(string $key): string;
}
