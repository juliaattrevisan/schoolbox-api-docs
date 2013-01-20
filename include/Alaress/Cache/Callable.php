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
//	protected $cacheHours = 1;

	public function __construct($callback, $params = array()) {
		//
		// Callback types:
		// anything passable to call_user_func
		$this->callback = $callback;
		$this->params   = $params;
		$this->setCacheFile($this->generateCacheFilename());
		$this->setCacheTime(60*60/**$this->getCacheHours()*/);
		$this->setup();
	}

	/* Show plain debugging messages
	 */
	private $verbose = false;

	/**
	 * Load contents from the cache
	 *
	 * @abstract
	 */
//	abstract public function load();


	/**
	 * Generate the filename that would be associated with this particular
	 * combination of user (author) and any optional keywords
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

//		$callback_key = array();
//		if (is_array())
		$key = array();
		$callable_name = null;
		if (is_callable($this->callback, false, $callable_name)) {
			$key[] = $callable_name;
			$key[] = $this->params;
		}
		$filename = Cfg::get('stream_dir') . '/method_' . md5(serialize($key)) . '.json';
		var_dump($filename);
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
	 * Set up cached contents of the url
	 * Overwrite an expired cached file if necessary
	 *
	 * @param $url
	 *
	 * @return bool
	 */
	protected function setup() {
		$cache_file = $this->getCacheFile();
		if (!file_exists($cache_file) || $this->isCacheExpired()) {
			$result = call_user_func_array($this->callback, $this->params);
//			var_dump($result);
//			$streamHandle = @fopen($url, 'r');
//			$cacheHandle = @fopen($cache_file, 'w');

//			/* Could not open stream source for reading
//			 */
//			if (!$streamHandle) {
//				return false;
//			}
//
//			/* Could not open cache file for writing
//			 */
//			if (!$cacheHandle) {
//				return false;
//			}

			/* Verbose debugging
			 */
			if ($this->getVerbose()) {
				echo 'Writing new ' . get_class($this) . ' file. ';
			}

			file_put_contents($cache_file, serialize($result));

//			while (!feof($streamHandle)) {
//				fwrite($cacheHandle, fread($streamHandle, 8192), 8192);
//			}
//
//			fclose($streamHandle);
//			fclose($cacheHandle);
		}
		else {

			/* Verbose debugging
			 */
			if ($this->getVerbose()) {
				echo 'Loading from cache. ';
			}
		}
	}
}
