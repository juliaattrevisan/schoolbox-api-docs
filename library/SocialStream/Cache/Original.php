<?php
/**
 * Alaress_Rss_Cache
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Cache;

class Original extends \Alaress_Cache_Abstract {

	/**
	 * A prefix for cached filenames
	 *
	 * @var string
	 */
	protected $cacheFilePrefix = 'socialstream_thumbnail';

	/**
	 * Hook method
	 *
	 * @return mixed|void
	 */
	protected function setup() {
		$this->setCachedTime(\Cfg::get('xml_cache_timeout'));
		$this->setLockTime($this->getLockTimeMultiplier() * $this->getCachedTime());
	}

	/**
	 * Figger out what the cached filename should be
	 *
	 * @return string
	 */
	protected function generateCachedFilename() {
		$urlDetection = new \SocialStream\UrlDetection($this->getUrl());

		/* Find what the file was called
		 */
		if ($urlDetection->isResourceUrl()) {
			return \Cfg::get('file_dir') . '/' . $urlDetection->getId() . '.dat';
		}

		return \Cfg::get('stream_dir') . '/socialstream/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl());
	}

	/**
	 * Save stream contents from the url
	 *
	 * @return bool
	 */
	protected function saveStreamContents() {

		/* Cache Factory -_-
		 */
		$urlDetection = new \SocialStream\UrlDetection($this->getUrl());

		if ($urlDetection->isLocalUrl()) {

			/* For images that existed in Storage since fopen can't
			 * open relative urls
			 */
			if ($urlDetection->isStorageUrl()) {
				$url = "http://{$_SERVER['HTTP_HOST']}{$this->getUrl()}";
				$this->processUrl($url);
			}

			/* Resource files would normally be copied into the socialstream cache location
			 * but to save space maybe we can redirect generateCachedFilename() instead,
			 * so it reads directly from the original's location.
			 */
		}
		else {
			$url = $this->getUrl();
			$this->processUrl($url);
		}
	}

	/**
	 * Process url using fopen in the usual way
	 *
	 * @param $url
	 * @return bool
	 */
	private function processUrl($url) {
		$streamHandle = fopen($url, 'r');
		$cacheHandle = @fopen($this->getCachedFile(), 'w');

		/* Could not open stream source for reading
		 * This will leave intended cache file size at 0 bytes
		 */
		if (!$streamHandle) {
			return false;
		}

		/* Verbose debugging
		 */
		//$this->notifyObservers('Writing new ' . get_class($this) . ' file: ' . $this->getCachedFile());

		while (!feof($streamHandle)) {
			fwrite($cacheHandle, fread($streamHandle, 8192), 8192);
		}

		fclose($streamHandle);
		fclose($cacheHandle);
	}
}
