<?php
/**
 * Port of old Config class to use PDO database instead of DeprecatedDatabase.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Config;

use OutOfRangeException;
use Schoolbox\Database\Factory;
use Schoolbox\IConfigLoader;

class Loader implements IConfigLoader
{
    /**
     * Cached config values.
     *
     * @var array
     */
    private $cache = [];


    /**
     * Constants
     *
     * @var array
     */
    private $consts = [];


    /**
     * @var self
     */
    private static $instance;


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
        if (isset($this->consts[$var])) {
            return $this->consts[$var];
        }
        if (defined($var)) {
            return constant($var);
        }
        if (empty($this->cache)) {
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
        $this->cache = [];
    }


    /**
     * Get a config variable
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
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
    public function __set($name, $value)
    {
        throw new OutOfRangeException(sprintf('"%s" is read-only', __CLASS__));
    }


    /**
     * Does this config setting exist or not?
     *
     * @param  mixed $name
     * @return bool
     */
    public function __isset($name): bool
    {
        if (isset($this->consts[$name])) {
            return true;
        }
        if (defined($name)) {
            return true;
        }
        if (empty($this->cache)) {
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
        $database = Factory::getInstanceInternal();
        $query    = $database->prepareQuery("SELECT name, value, field_id FROM config");
        $query->execute();
        foreach ($query as $row) {
            $this->cache[$row['name']] = $this->hydrateValue((int)$row['field_id'], $row['value']);
        }
    }


    /**
     * Light-weight hydration to respect boolean fields
     *
     * @param int   $fieldId
     * @param mixed $value
     * @return mixed
     */
    private function hydrateValue(int $fieldId, $value)
    {
        switch ($fieldId) {
            case ConfigMapper::FIELD_BOOLEAN:
            case ConfigMapper::FIELD_CHECKBOX:
                return $value === null ? null : (bool)$value;
            default:
        }
        return $value;
    }
}
