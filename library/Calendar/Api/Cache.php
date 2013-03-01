<?php
/**
 * Calendar_Api_Cache
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Api_Cache extends Alaress_Cache_Abstract {

	/* Chooze the number of hours to cache the tweets.
	 * This is converted into seconds via $cacheTime
	 */
	protected $cacheHours = 1;

	/* 9 seconds for testing
	 */
//	protected $cacheHours = 0.0025;

	/* Derived cache settings, don't need to change these
	 */
	protected $cacheFile = '';
	protected $cacheTime = 0;

	/**
	 * The url of the stream you just got
	 *
	 * @var
	 */
	private $url = '';

	/**
	 * Constructor
	 *
	 * @param $url
	 */
	public function __construct($url) {
		$this->setUrl($url);

		/* Interpret cache details
		 */
		$this->setCacheFile($this->generateCacheFilename($url));
		$this->setCacheTime(60*60*$this->getCacheHours());

		/* Set up a cached version of the url
		 */
		$this->setup($url);
	}

	/**
	 * Generate the filename that would be associated with this url
	 *
	 * @return string
	 */
	protected function generateCacheFilename() {
		$filename = Cfg::get('stream_dir') . '/' . 'calendar_' . md5($this->getUrl()) . '.txt';

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
			return file_get_contents($path);
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Get cache hours
	 *
	 * @return int
	 */
	private function getCacheHours() {
		return $this->cacheHours;
	}

	/**
	 * Set url
	 *
	 * @param  $url
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
