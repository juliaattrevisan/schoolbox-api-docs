<?php
/**
 * Alaress_Rss_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Rss_Cache {

	/**
	 * Constructor
	 *
	 * @param  $cache_file
	 * @param  $cache_time
	 * @param  $url
	 */
	public function __construct($cache_file, $cache_time, $url) {
		$this->setCacheFile($cache_file);
		$this->setCacheTime($cache_time);

		/* Set up a cached version of the url
		 */
		$this->setup($url);
	}

	/**
	 * Set up cached contents of the url
	 * Overwrite an expired cached file if necessary
	 *
	 * @param  $cache_file
	 * @param  $cache_time
	 * @param  $url
	 * @return bool
	 */
	private function setup($url) {
        if (!file_exists($this->getCacheFile()) || $this->isCacheExpired()) {
            $xml_handle = @fopen($url, 'r');
            $cache_handle = @fopen($this->getCacheFile(), 'w');

			if (!$xml_handle || !$cache_handle) {
				return false;
			}

            while (!feof($xml_handle)) {
                fwrite($cache_handle, fread($xml_handle, 8192), 8192);
            }

            fclose($xml_handle);
            fclose($cache_handle);
        }
	}

	/**
	 * Determine if the cached file has expired
	 *
	 * @return bool
	 */
	private function isCacheExpired() {
		if (filectime($this->getCacheFile()) < (time() - $this->getCacheTime())) {
			return true;
		}

		return false;
	}

	/**
	 * Load contents from the cache
	 *
	 * @param  $cache_file
	 * @return void
	 */
	public function load() {
		libxml_use_internal_errors(true);

		return new SimpleXMLElement($this->getCacheFile(), null, true);
	}

	/**
	 * Remove the cached file
	 *
	 * @return void
	 */
	public function remove() {
		unlink($this->getCacheFile());
	}

	/**
	 * Set cache file
	 *
	 * @param  $val
	 * @return void
	 */
	private function setCacheFile($val) {
		$this->cacheFile = $val;
	}

	/**
	 * Get cache file
	 *
	 * @return
	 */
	private function getCacheFile() {
		return $this->cacheFile;
	}

	/**
	 * Set cache time
	 *
	 * @param  $val
	 * @return void
	 */
	private function setCacheTime($val) {
		$this->cacheTime = $val;
	}

	/**
	 * Get cache time
	 *
	 * @return
	 */
	private function getCacheTime() {
		return $this->cacheTime;
	}
}
