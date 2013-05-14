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

	/**
	 * A prefix for cached filenames
	 *
	 * @var string
	 */
	protected $cacheFilePrefix = 'twitter';

	/**
	 * Contains specific URL parms for twitter requests
	 *
	 * @var array
	 */
	protected $parameters;

	/**
	 * Hook method
	 *
	 * @return mixed|void
	 */
	protected function setup() {
		$this->setCachedTime(Cfg::get('twitter_api_caching'));
		$this->setLockTime($this->getLockTimeMultiplier() * $this->getCachedTime());
	}

	public function init($url, $params) {
		$this->setParameters($params);
		parent::init($url);
		return $this;
	}

	/**
	 * Overwrites native cache stream saver with OAuth fetch system
	 */
	protected function saveStreamContents() {
		$twitterCon = new Alaress_Twitter_Loader_OAuth(
			Cfg::get('twitter_api_consumer_key'),
			Cfg::get('twitter_api_consumer_secret'),
			Cfg::get('twitter_api_oauth_token'),
			Cfg::get('twitter_api_oauth_token_secret')
		);

		$result = $twitterCon->load($this->getUrl(), $this->getParameters());
		file_put_contents($this->getCachedFile(), serialize($result));
	}

	/**
	 * Load function for converting cached data into useable data array
	 *
	 * @return array
	 */
	public function load() {
		return unserialize($this->getStreamContents());
	}

	/**
	 * Figger out what the cached filename should be
	 *
	 * @return string
	 */
	protected function generateCachedFilename() {
		return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl().serialize($this->getParameters())) . '.txt';
	}

	/**
	 * Figger out what the lock filename should be
	 *
	 * @return string
	 */
	protected function generateLockFilename() {
		return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl().serialize($this->getParameters())) . '_lock.txt';
	}

	/**
	 * Get for parameters
	 *
	 * @return string
	 */
	protected function getParameters() {
		return $this->parameters;
	}

	/**
	 * Setter for params
	 *
	 * @vars string
	 */
	protected function setParameters($params) {
		return $this->parameters = $params;
	}
}
