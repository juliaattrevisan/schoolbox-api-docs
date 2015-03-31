<?php
/**
 * Port of old Config class to use PDO database instead of DeprecatedDatabase.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2014 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Config;

use ArrayAccess;
use OutOfRangeException;
use Schoolbox\IConfigLoader;

class Loader implements IConfigLoader, ArrayAccess {

    /**
     * @var array
     */
    protected $consts = array();

    /**
     * @var array
     */
    protected $cache = array();

    /**
     * Set up constant paths
     */
    public function __construct() {
        $w = realpath(__DIR__ . "/../../..");
        $this->consts = array(
            'siteRoot' => $w,
            'webRoot'  => "$w/www",
            'webBase'  => ''
        );
    }

    /**
     * Get a config variable
     *
     * @param string $var
     * @return mixed
     */
    public function get($var) {
        if (empty($this->cache)) {
            $this->cache = $this->getData();
        }
        if (isset($this->cache[$var])) {
            return $this->cache[$var];
        }
        elseif (isset($this->consts[$var])) {
            return $this->consts[$var];
        }
        elseif (defined($var)) {
            return constant($var);
        }
        else {
            return '';
        }
    }

    /**
     * Loads data direct from database
     *
     * @return array
     */
    protected function getData() {
        $query = \Database::getInstance()->prepare('SELECT `name`, `value` FROM `config`');
        $query->execute();
        $result = array();
        foreach ($query as $row) {
            $result[$row['name']] = $row['value'];
        }
        return $result;
    }

    /**
     * Does this offset exist or not?
     *
     * Currently it is impossible to tell from Cfg::get() if a config option is
     * blank or it does not exist, so this always returns true.
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset) {
        return true;
    }

    /**
     * Gets this config option.
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset) {
        return $this->get($offset);
    }

    /**
     * Config options are not writable from this class.
     *
     * @throws OutOfRangeException
     */
    public function offsetSet($offset, $value) {
        throw new OutOfRangeException('Schoolbox\\Config\\Loader is read-only');
    }

    /**
     * Config options are not writable from this class.
     *
     * @throws OutOfRangeException
     */
    public function offsetUnset($offset) {
        throw new OutOfRangeException('Schoolbox\\Config\\Loader is read-only');
    }

    /**
     * Drop any cached config
     *
     * @return void
     */
    public function dropCache() {
        $this->cache = array();
    }

}
