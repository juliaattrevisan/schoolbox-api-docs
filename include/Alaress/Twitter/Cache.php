<?php
/**
 * Alaress_Twitter_Cache
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Twitter_Cache extends Alaress_Cache_Abstract {

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

	private $user = '';
	private $keywords = '';

	/**
	 * Constructor
	 *
	 * @param $user
	 * @param $keywords
	 * @param $url
	 */
	public function __construct($user, $keywords, $url) {
		$this->setUser($user);
		$this->setKeywords($keywords);

		/* Interpret cache details
		 */
		$this->setCacheFile($this->generateCacheFilename());
		$this->setCacheTime(60*60*$this->getCacheHours());

		/* Set up a cached version of the url
		 */
		$this->setup($url);
	}

	/**
	 * Generate the filename that would be associated with this particular
	 * combination of user (author) and any optional keywords
	 *
	 * @internal param $author
	 * @return string
	 */
	protected function generateCacheFilename() {
		$filename = Cfg::get('stream_dir') . '/' . 'twitter_' . md5($this->getUser().$this->getKeywords()) . '.json';

		return $filename;
	}

	/**
	 * Load contents from the cache
	 *
	 * @return void
	 */
	public function load() {
		$path = $this->getCacheFile();

		return file_get_contents($path);
	}

	/* Getters/Setters
	 */

	/**
	 * Set keywords
	 *
	 * @param $keywords
	 */
	private function setKeywords($keywords) {
		$this->keywords = $keywords;
	}

	/**
	 * Get keywords
	 *
	 * @return null
	 */
	private function getKeywords() {
		return $this->keywords;
	}

	/**
	 * Set user
	 *
	 * @param $user
	 */
	private function setUser($user) {
		$this->user = $user;
	}

	/**
	 * Get user
	 *
	 * @return null
	 */
	private function getUser() {
		return $this->user;
	}

	/**
	 * Get cache hours
	 *
	 * @return int
	 */
	private function getCacheHours() {
		return $this->cacheHours;
	}
}
