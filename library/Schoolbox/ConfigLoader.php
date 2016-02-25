<?php
/**
 * Non-static configuration loader for Schoolbox models and objects.
 *
 * Uses old static config under the hood, but is not a singleton so it is
 * able to be mocked in unit tests.
 *
 * (ArrayAccess implementation was added mainly for ease of use with Twig.
 *  config.$option works with ArrayAccess, whereas it didn't before.)
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox;
use Schoolbox;
use ArrayAccess;
use OutOfRangeException;
use Schoolbox\Config\ConfigMapper;

class ConfigLoader implements IConfigLoader, ArrayAccess {

    /**
     * Gets the config option.
     *
     * @param  mixed $var
     * @return mixed
     */
    public function get($var) {
        return ConfigMapper::getInstance()->get($var);
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
     * The internal config class is not writable from here.
     *
     * @throws OutOfRangeException
     */
    public function offsetSet($offset, $value) {
        throw new OutOfRangeException('ConfigLoader object is read-only');
    }

    /**
     * The internal config class is not writable from here.
     *
     * @throws OutOfRangeException
     */
    public function offsetUnset($offset) {
        throw new OutOfRangeException('ConfigLoader object is read-only');
    }

	/**
	 * Drop any cached config
	 *
	 * @return void
	 */
	public function dropCache() {
		// noop
	}

}