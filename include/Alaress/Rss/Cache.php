<?php
/**
 * Alaress_Rss_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Rss_Cache extends Alaress_Cache_Abstract {
	protected $cacheFile = null;
	protected $cacheTime = null;

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
	 * Load contents from the cache
	 *
	 * @return void
	 */
	public function load() {
		libxml_use_internal_errors(true);

		return new SimpleXMLElement($this->getCacheFile(), null, true);
	}

	/**
	 * The cache filename to use for this rss feed
	 */
	protected function generateCacheFilename() {
	}
}
