<?php
/**
 * Alaress_Cache_MethodCall
 *
 * Common functionality for cacheable method calls.
 * Pass a callable and its parameters, this class will do the rest.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @version   SVN: $Id$
 */
class Alaress_Cache_Callable extends Alaress_Cache_Abstract {
	protected $callback = null;
	protected $params   = array();

	/**
	 * Creates the cache object.
	 *
	 * @param callable $callback The callback to cache the result of.
	 *                           @see http://php.net/manual/en/language.types.callable.php
	 * @param array    $params   The parameters to pass to $callback
	 * @param int      $lifetime The time the cache lasts, defaults to 1 hour.
	 */
	public function __construct($callback, $params = array(), $lifetime = 3600) {
		//
		// Callback types:
		// anything passable to call_user_func
		$this->callback = $callback;
		$this->params   = $params;
		$this->setCacheFile($this->generateCacheFilename());
		$this->setCacheTime($lifetime);
		$this->setup();
	}

	/* Show plain debugging messages
	 */
	private $verbose = false;


	/**
	 * Generate the filename for this callback and parameters.
	 *
	 * @internal param $author
	 * @return string
	 */
	protected function generateCacheFilename() {
		//
		// Callback types:
		// function
		// classname::method
		// array(classname, method)
		// array(object, method)
		// callable


		$key = array();
		$callable_name = null;
		$is_callable = is_callable($this->callback, false, $callable_name);
		$key[] = $callable_name;
		$key[] = $this->params;
		$filename = Cfg::get('stream_dir') . '/callable_' . md5(serialize($key)) . '.txt';
		return $filename;
	}


	/**
	 * Load contents from the cache
	 *
	 * @return void
	 */
	public function load() {
		$path = $this->getCacheFile();

		if (file_exists($path)) {
			return unserialize(file_get_contents($path));
		}

		return false;
	}


	/**
	 * Sets up the cached result.
	 *
	 * If the cache file doesn't exist or is expired, calls the callback passed
	 * into the constructor, and loads that value into the cache file.
	 *
	 * @return bool
	 */
	protected function setup() {
		$cache_file = $this->getCacheFile();
		if (!file_exists($cache_file) || $this->isCacheExpired()) {
			$result = call_user_func_array($this->callback, $this->params);
			if ($this->getVerbose()) {
				echo 'Writing new ' . get_class($this) . ' file. ';
			}
			return file_put_contents($cache_file, serialize($result));
		}
		else {
			if ($this->getVerbose()) {
				echo 'Loading from cache. ';
			}
		}
		return true;
	}
}
