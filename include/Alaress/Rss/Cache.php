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

	private $url = '';

	/**
	 * Constructor
	 *
	 * @param  $cache_time
	 * @param  $url
	 */
	public function __construct($cache_time, $url) {
		$this->setUrl($url);

		$this->setCacheFile($this->generateCacheFilename());
		$this->setCacheTime($cache_time);

		/* Set up a cached version of the url
		 */
		$this->setup($url);
	}

	/**
	 * The cache filename to use for this rss feed
	 *
	 * @return string
	 */
	protected function generateCacheFilename() {
		$filename = Cfg::get('stream_dir') . '/' . 'rss_' . md5($this->getUrl()) . '.xml';

		return $filename;
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

	/* Getters/Setters
	 */

	/**
	 * Set url
	 *
	 * @param $url
	 */
	private function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	private function getUrl() {
		return $this->url;
	}
}
