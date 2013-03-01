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
	protected $cacheFilePrefix = '';

	/**
	 * The duration of a cached version before it becomes expired
	 * This is a duration measured in seconds
	 *
	 * @var null
	 */
	protected $cachedTime = 0;

	/**
	 * The duration of a lock file before it becomes expired
	 * This is a duration measured in seconds
	 *
	 * @var int
	 */
	protected $lockTime = 0;

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
	 * Constructor
	 *
	 * This is deliberately empty so you can instantiate an instance and set custom options
	 * such as expiry durations, urls, etc
	 */
	final public function __construct() {

		/* Example usage with multiple settings

		$cache = new Alaress_Cache_Abstract2();

		$contents = $cache->setCachedTime(7200)
						->setLockTime(3600)
						->setVerbose(true)
						->init('http://www.google.com')
						->getStreamContents();
		*/
	}

	/**
	 * Setup method
	 *
	 * @param $url
	 * @return \Alaress_Cache_Abstract2
	 */
	public function init($url) {
		$this->setUrl($url);

		/* Determine the filenames of files that could be used
		 */
		$this->setCachedFile($this->generateCachedFilename());
		$this->setLockFile($this->generateLockFilename());

		/* Retrieve stream contents
		 */
		$this->retrieveStreamContents();

		return $this;
	}

	/**
	 * Get contents of a stream using a lockfile-based mechanism
	 */
	private function retrieveStreamContents() {

		/* If the cached file exists, check its expiry
		 */
		if (file_exists($this->getCachedFile())) {
			if ($this->isExpired($this->getCachedFile(), $this->getCachedTime())) {
				if (file_exists($this->getLockFile())) {
					if ($this->isExpired($this->getLockFile(), $this->getCachedTime())) {

						/* newCacheUpdate()
						 */
						$this->log("Cache expired, lockfile expired perhaps due to timeout or halted. Regenerate everything.");
						$this->newCacheUpdate();

					}
					else {
						/* Load content from cached version
						 */
						$this->log("Cache expired, lockfile still fresh. Loading from cache while in process of retrieving new stream contents.");
						$this->setStreamContents(file_get_contents($this->getCachedFile()));
					}
				}
				else {
					/* newCacheUpdate()
					 */
					$this->log("Cache expired, lockfile doesn't exist. Regenerate everything.");
					$this->newCacheUpdate();
				}
			}

			else {
				/* Load content from cached version
				 */
				$this->log('Cached file exists and is still fresh. Loading from cache.');
				$this->setStreamContents(file_get_contents($this->getCachedFile()));
			}
		}
		/* First load
		 */
		else {
			$this->newCacheUpdate();
		}
	}

	/**
	 * Logging mechanism
	 *
	 * @param $text
	 */
	private function log($text) {
		if ($this->getVerbose()) {
			echo $text . "<BR>";
		}
	}

	/**
	 * Sam's new cache update :P
	 *
	 * @return Alaress_Cache_Abstract2
	 */
	private function newCacheUpdate() {

		/* Remove lock files for this stream url
		 */
		$this->log('Removing lock file.');
		$this->remove($this->getLockFile());

		/* Create new lock file for this stream url
		 */
		$this->log('Creating lock file.');
		$this->createLockFile();

		/* Fetch new content from stream url
		 * Save content to cached file
		 */
		$this->log('Saving stream contents to cache file.');
		$this->saveStreamContents();

		/* Remove lock file for this stream url
		 */
		$this->log('Removing lock file.');
		$this->remove($this->getLockFile());

		return $this;
	}

	/**
	 * Save stream contents from the url
	 *
	 * @return bool
	 */
	private function saveStreamContents() {
		$streamHandle = @fopen($this->getUrl(), 'r');
		$cacheHandle = @fopen($this->getCachedFile(), 'w');

		/* Could not open stream source for reading
		 */
		if (!$streamHandle) {
			return false;
		}

		/* Verbose debugging
		 */
		$this->log('Writing new ' . get_class($this) . ' file. ');

		while (!feof($streamHandle)) {
			fwrite($cacheHandle, fread($streamHandle, 8192), 8192);
		}

		fclose($streamHandle);
		fclose($cacheHandle);
	}

	/**
	 * Create an empty file to indicate that a stream retrieval is in progress
	 */
	private function createLockFile() {
		$filehandle = @fopen($this->generateLockFilename(), 'w');
		fclose($filehandle);
	}

	/**
	 * Remove the cached file
	 *
	 * @param $file
	 * @return Alaress_Cache_Abstract2
	 */
	private function remove($file) {
		@unlink($file);

		return $this;
	}

	/**
	 * Determine if the cached file has expired
	 *
	 * @param $file
	 * @param $duration
	 * @return bool
	 */
	private function isExpired($file, $duration) {
		$now = time();

		/* Difference between now and when file was created
		 */
		$difference = $now - filectime($file);

		/* How much time is remaining
		 */
		$remaining = $duration - $difference;

		$this->log(get_class($this) . ' file expires in ' . $remaining . ' seconds.');
		$this->log('[' . $difference . '] ' . $now . ' - ' . filectime($file));

		if (filectime($file) < ($now - $duration)) {
			$this->log($file . ' expired.');
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
	 * Figger out what the lock filename should be
	 *
	 * @return string
	 */
	private function generateLockFilename() {
		return Cfg::get('stream_dir') . '/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl()) . '_lock.txt';
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

	/* Getters/Setters
	 */

	/**
	 * Set cache file prefix
	 *
	 * @param string $cacheFilePrefix
	 */
	private function setCacheFilePrefix($cacheFilePrefix) {
		$this->cacheFilePrefix = $cacheFilePrefix;
	}

	/**
	 * Get cache file prefix
	 *
	 * @return string
	 */
	private function getCacheFilePrefix() {
		return $this->cacheFilePrefix;
	}

	/**
	 * Set cached file
	 *
	 * @param string $cachedFile
	 */
	private function setCachedFile($cachedFile) {
		$this->cachedFile = $cachedFile;
	}

	/**
	 * Get cached file
	 *
	 * @return string
	 */
	private function getCachedFile() {
		return $this->cachedFile;
	}

	/**
	 * Set cached time
	 *
	 * @param null $cachedTime
	 * @return \Alaress_Cache_Abstract2
	 */
	public function setCachedTime($cachedTime) {
		$this->cachedTime = $cachedTime;

		return $this;
	}

	/**
	 * Get cached time
	 *
	 * @return null
	 */
	private function getCachedTime() {
		return $this->cachedTime;
	}

	/**
	 * Set lock file
	 *
	 * @param string $lockFile
	 */
	private function setLockFile($lockFile) {
		$this->lockFile = $lockFile;
	}

	/**
	 * Get lock file
	 *
	 * @return string
	 */
	private function getLockFile() {
		return $this->lockFile;
	}

	/**
	 * Set stream contents
	 *
	 * @param string $streamContents
	 */
	private function setStreamContents($streamContents) {
		$this->streamContents = $streamContents;
	}

	/**
	 * Get stream contents
	 *
	 * @return string
	 */
	public function getStreamContents() {
		return $this->streamContents;
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

	/**
	 * Set verbose
	 *
	 * @param boolean $verbose
	 * @return \Alaress_Cache_Abstract2
	 */
	public function setVerbose($verbose) {
		$this->verbose = $verbose;

		return $this;
	}

	/**
	 * Get verbose
	 *
	 * @return boolean
	 */
	private function getVerbose() {
		return $this->verbose;
	}

	/**
	 * Set lock time
	 *
	 * @param int $lockTime
	 * @return \Alaress_Cache_Abstract2
	 */
	public function setLockTime($lockTime) {
		$this->lockTime = $lockTime;

		return $this;
	}

	/**
	 * Get lock time
	 *
	 * @return int
	 */
	private function getLockTime() {
		return $this->lockTime;
	}
}

