<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Config;

use Schoolbox\IConfigLoader;

/**
 * Config loader which simply loads config variables from an array passed at
 * construct time.
 *
 * This config loader does not attempt to access the database, thus making it
 * appropriate for use in unit tests.
 */
class ArrayLoader implements IConfigLoader
{
    /**
     * Constructor
     *
     * @param array<string, mixed> $cache Config vars to use in this loader.
     */
    public function __construct(private array $cache = [])
    {
    }

    /**
     * Get a config variable
     *
     * @param string $var
     * @return mixed
     */
    public function get($var)
    {
        return $this->cache[$var] ?? null;
    }

    /**
     * Dropping the cache does nothing, as this loader doesn't have a mechanism
     * to reload its config vars.
     *
     * @return void
     */
    public function dropCache()
    {
    }
}
