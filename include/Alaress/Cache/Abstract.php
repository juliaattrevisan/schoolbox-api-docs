<?php
/**
 * Alaress_Cache_Abstract
 *
 * Common functionality for cacheable streams e.g. RSS, Twitter
 *
 * At this stage RSS and Twitter use this abstract, but their constructor
 * interfaces are different. They should really be the same, and there
 * should be an abstract method here generateCacheFilename()
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Alaress_Cache_Abstract {
	protected $cacheFile = null;
	protected $cacheTime = null;

	/* Show plain debugging messages
	 */
	private $verbose = false;

	/**
	 * Load contents from the cache
	 *
	 * @abstract
	 */
	abstract public function load();

	/**
	 * Generate the filename used for caching
	 *
	 * @abstract
	 */
	abstract protected function generateCacheFilename();

	/**
	 * Set up cached contents of the url
	 * Overwrite an expired cached file if necessary
	 *
	 * @param $url
	 *
	 * @return bool
	 */
	protected function setup($url) {
		if (!file_exists($this->getCacheFile()) || $this->isCacheExpired()) {
			$streamHandle = @fopen($url, 'r');
			$cacheHandle = @fopen($this->getCacheFile(), 'w');

			/* Could not open stream source for reading
			 */
			if (!$streamHandle) {
				return false;
			}

			/* Could not open cache file for writing
			 */
			if (!$cacheHandle) {
				return false;
			}

			/* Verbose debugging
			 */
			if ($this->getVerbose()) {
				echo 'Writing new ' . get_class($this) . ' file. ';
			}

			while (!feof($streamHandle)) {
				fwrite($cacheHandle, fread($streamHandle, 8192), 8192);
			}

			fclose($streamHandle);
			fclose($cacheHandle);
		}
		else {

			/* Verbose debugging
			 */
			if ($this->getVerbose()) {
				echo 'Loading from cache. ';
			}
		}
	}

	/**
	 * Remove the cached file
	 *
	 * @return void
	 */
	public function remove() {
		@unlink($this->getCacheFile());
	}

	/**
	 * Determine if the cached file has expired
	 *
	 * @return bool
	 */
	protected function isCacheExpired() {
		if ($this->getVerbose()) {
			echo get_class($this) . ' file expires in ' . ((60*60*1) - (time() - filectime($this->getCacheFile()))) . ' seconds. ';
		}

		if (filectime($this->getCacheFile()) < (time() - $this->getCacheTime())) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Cache folder
	 *
	 * @return string
	 */
	protected function getCacheFolder() {
		return $this->cacheFolder;
	}

	/**
	 * Set cache file
	 *
	 * NB: This is the full path to the file
	 * (e.g. /var/www/schoolbox/data/cache/file.txt)
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setCacheFile($val) {
		$this->cacheFile = $val;
	}

	/**
	 * Get cache file
	 *
	 * @return
	 */
	protected function getCacheFile() {
		return $this->cacheFile;
	}

	/**
	 * Set cache time
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setCacheTime($val) {
		$this->cacheTime = $val;
	}

	/**
	 * Get cache time
	 *
	 * @return
	 */
	protected function getCacheTime() {
		return $this->cacheTime;
	}

	/**
	 * Get verbose
	 *
	 * @return bool
	 */
	protected function getVerbose() {
		return $this->verbose;
	}
}
