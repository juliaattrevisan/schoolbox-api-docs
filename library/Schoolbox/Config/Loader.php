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
     * @var self
     */
    private static $instance;


    /**
     * Cached config values.
     *
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
        if (!isset(self::$allConfigs)) {
            $this->loadCache();
        }
        return self::$allConfigs[$var] ?? null;
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
        if (!isset(self::$allConfigs)) {
            $this->loadCache();
        }
        return isset(self::$allConfigs[$name]);
    }


    /**
     * Load cached config values
     */
    private function loadCache(): void
    {
        if (!isset(self::$allConfigs)) {
            self::$allConfigs = ConfigMapper::getInstance()->findAllConfigs();
        }
    }
}
