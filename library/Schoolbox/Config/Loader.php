<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Config;

use OutOfRangeException;
use Schoolbox\IConfigLoader;

/**
 * The standard interface for retrieving Schoolbox configuration settings.
 */
class Loader implements IConfigLoader
{
    /**
     * Cached config values.
     *
     * @var array
     */
    private $cache;


    /**
     * @var self
     */
    private static $instance;


    /**
     * @var array
     */
    private static $allConfigs;


    /**
     * Constructor
     */
    public function __construct()
    {
        // TODO inject DB access class here
    }


    /**
     * Temporary getInstance method, for replacing
     * `\Schoolbox\Config\ConfigMapper::getInstance`.
     *
     * @return static
     */
    public static function getInstance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * Get a config variable
     *
     * @param string $var
     * @return mixed
     */
    public function get($var)
    {
        if (!isset($this->cache)) {
            $this->loadCache();
        }
        return $this->cache[$var] ?? null;
    }


    /**
     * Drop any cached config
     *
     * @return void
     */
    public function dropCache()
    {
        self::$allConfigs = null;
    }


    /**
     * Get a config variable
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }


    /**
     * Config options are not writable here.
     *
     * @param string $name
     * @param mixed  $value
     * @throws OutOfRangeException
     */
    public function __set(string $name, $value)
    {
        throw new OutOfRangeException(sprintf('"%s" is read-only', __CLASS__));
    }


    /**
     * Does this config setting exist or not?
     *
     * @param  mixed $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        if (!isset($this->cache)) {
            $this->loadCache();
        }
        return isset($this->cache[$name]);
    }


    /**
     * Load cached config values
     */
    private function loadCache(): void
    {
        // Sorry, I know this should be injected but the amount of configs
        // strewn everywhere make that impossible
        $this->cache = self::findAllConfigs();
    }


    /**
     * Static source for all config vars
     *
     * Added so that subsequent lookups don't hit the DB (which in the case of
     * worker processes, may have had its connection terminated)
     *
     * @return array
     */
    private static function findAllConfigs(): array
    {
        if (!isset(self::$allConfigs)) {
            self::$allConfigs = ConfigMapper::getInstance()->findAllConfigs();
        }
        return self::$allConfigs;
    }
}
