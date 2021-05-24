<?php

/**
 * Test config loader
 *
 * This config loader does not attempt to access the database, thus making it
 * appropriate for use in unit tests.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Config;

use Schoolbox\IConfigLoader;

class TestLoader implements IConfigLoader
{
    /**
     * The config variables available to this config loader.
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Constructor
     *
     * @param array $cache Config variables to use in this test loader
     */
    public function __construct($cache = [])
    {
        $this->cache = $cache;
    }

    /**
     * Get a config variable
     *
     * @param string $var
     * @return mixed
     */
    public function get($var)
    {
        return isset($this->cache[$var]) ? $this->cache[$var] : null;
    }

    /**
     * Drop any cached config
     *
     * @return void
     */
    public function dropCache()
    {
    }
}
