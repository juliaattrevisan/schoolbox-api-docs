<?php
/**
 * Alaress_Cache_Abstract2
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Alaress_Cache_Abstract2 {

	/**
	 * The url of the stream you just got
	 *
	 * @var
	 */
	protected $url = '';

	/**
	 * A prefix for cached filenames
	 *
	 * @var string
	 */
	protected $cacheFilePrefix = 'calendar';

	/**
	 * The duration of a cached version before it becomes expired
	 * This is a duration measured in hours
	 *
	 * @var null
	 */
	protected $cachedTime = 0;

	/**
	 * Show plain debugging messages
	 *
	 * @var bool
	 */
	protected $verbose = false;

	/**
	 * The temporary lock file for the specified url stream
	 *
	 * @var string
	 */
	private $lockFile = '';

	/**
	 * A cached version of the url stream
	 *
	 * @var string
	 */
	private $cachedFile = '';

	/**
	 * The contents of the stream upon request. The contents come from the cache
	 *
	 * @var string
	 */
	private $streamContents = '';

	/**
	 * Setup method
	 *
	 * @param $url
	 */
	protected function setup($url) {
		$this->setUrl($url);
		$this->setCachedFile($this->generateCachedFilename());

		/* If the cached file exists, check its expiry
		 */
		if (file_exists($this->getCachedFile())) {
			if ($this->isExpired($this->getCachedFile())) {

				if ($this->lockFileExists()) {
					if ($this->isExpired($this->getLockFile())) {

						/* newCacheUpdate()
						 */
					}
					else {
						/* Load content from cached version
						 */
						if ($this->getVerbose()) {
							echo "Lock file exists, but it's not expired. Loading from cache.";
						}

						$this-setStreamContents(file_get_contents($this->getCachedFile()));
					}
				}
				else {
					/* newCacheUpdate()
					 */
				}
			}

			else {
				/* Load content from cached version
				 */
				if ($this->getVerbose()) {
					echo "Cached file exists and is not expired. Loading from cache.";
				}

				$this-setStreamContents(file_get_contents($this->getCachedFile()));
			}
		}

		return $this;
	}

	private function newCacheUpdate() {

		/* Remove lock files for this stream url
		 */

		/* Create new lock file for this stream url
		 */

		/* Fetch new content from stream url
		 */

		/* Save content to cached file
		 */

		/* Remove lock file for this stream url
		 */
	}

	/**
	 * Determine if the cached file has expired
	 *
	 * @param $file
	 * @return bool
	 */
	private function isExpired($file) {
		if ($this->getVerbose()) {
			echo get_class($this) . ' file expires in ' . ((60*60*1) - (time() - filectime($file))) . ' seconds. ';
		}

		if (filectime($file) < (time() - $this->getCachedTime())) {
			return true;
		}

		return false;
	}

	/**
	 * Figger out what the cached filename should be
	 *
	 * @return string
	 */
	private function generateCachedFilename() {
		return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl()) . '.txt';
	}

	/**
	 * Hashing method. By default it uses md5, but subclasses can use whatever
	 * hashing method they want such as sha1
	 *
	 * @param $data
	 * @return string
	 */
	protected function encode($data) {
		return md5($data);
	}
}

