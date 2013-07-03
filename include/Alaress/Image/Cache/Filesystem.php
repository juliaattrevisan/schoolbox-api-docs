<?php
/**
 * Alaress_Image_Cache_Filesystem
 *
 * Load images from some arbitrary path on the filesystem
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Alaress_Image_Cache_Filesystem implements Alaress_Image_Cache_Interface {

	/* The image to load
	 */
	private $filename = null;

	/* The $key is a way to uniquely associate a cached image with a particular
	 * series of transformations applied to it. In this caching implementation,
	 * it's the fileID concatenated with the name of the transformation applied.
	 *
	 * e.g. 51-Web.dat
	 */
	private $key = null;

	/* This is where cached images are stored
	 */
	private $cacheLocation = '../cache/image';

	/* The filepath for processed image output
	 * Defaults to /tmp if it couldn't figure out where the file goes
	 */
	private $cachedFileLocation = '/tmp';

	/* Remember whether we loaded from the cached file. If it was loaded from the
	 * cache, we can skip transformations as we assume they have been done in the past.
	 * Otherwise we would be re-transforming an already-transformed image repeatedly
	 */
	private $loadedFromCache = false;

	/**
	 * Constructor
	 *
	 * Remembers what image you are trying to load from cache
	 *
	 * @param $filename
	 * @param $key
	 */
	public function __construct($filename, $key) {
		$this->setFilename($filename);
		$this->setKey($key);
	}

	/**
	 * Load an image by an id
	 *
	 * @return mixed
	 */
	public function load() {
		if (file_exists($this->getFilename())) {
			$info = getimagesize($this->getFilename());

			$info['filepath'] = $this->getFilename();
			$info['filename'] = basename($this->getFilename());
			$info['size'] = filesize($this->getFilename());

			/* We do this step if we are using Filesystem Cache directly
			 */

			/* If this particular transformation key existed at some point,
			 * this is what it's name would be
			 *
			 * e.g. <md5_hash>-Web.dat, <md5_hash>-ChickenPox.dat, etc. where Web and ChickenPox
			 * correspond to a series of transformations
			 */
			$cachedFilename = md5($this->getFilename()) . '-' . $this->getKey() . '.dat';

			/* This is where you'd find it in the filesystem if it existed previously
			 */
			$this->setCachedFileLocation($this->getCacheLocation() . '/' . $cachedFilename);

			/* Load a preprocessed version directly from cache
			 */
			if (file_exists($this->getCachedFileLocation())) {
				$this->setLoadedFromCache(true);

				$info = getimagesize($this->getCachedFileLocation());
				$info['filepath'] = $this->getCachedFileLocation();
				$info['filename'] = $cachedFilename;
				$info['size'] = filesize($this->getCachedFileLocation());
			}

			return $this->standardise($info);
		}
		else {
			die('Could not read file.');
		}
	}

	/**
	 * Standard temporary interface
	 *
	 * @param $imageArray
	 * @return array
	 */
	public function standardise($imageArray) {
		return array(
			'fileID' => $imageArray['filename'],
			'filepath' => $imageArray['filepath'],
			'filename' => $imageArray['filename'],
			'mime' => $imageArray['mime'],
			'size' => $imageArray['size'],
		);
	}

	/**
	 * Set filename
	 *
	 * @param $filename
	 */
	private function setFilename($filename) {
		$this->filename = $filename;
	}

	/**
	 * Get filename
	 *
	 * @return null
	 */
	private function getFilename() {
		return $this->filename;
	}

	/**
	 * Set original image location
	 *
	 * @param $originalsLocation
	 */
	private function setOriginalsLocation($originalsLocation) {
		$this->originalsLocation = $originalsLocation;
	}

	/**
	 * Get original image location
	 *
	 * @return string
	 */
	private function getOriginalsLocation() {
		return $this->originalsLocation;
	}

	/**
	 * Find out whether we loaded from cache or not
	 *
	 * @return bool
	 */
	public function isLoadedFromCache() {
		return $this->loadedFromCache;
	}

	/**
	 * Set cached file location
	 *
	 * @param $cachedFileLocation
	 */
	private function setCachedFileLocation($cachedFileLocation) {
		$this->cachedFileLocation = $cachedFileLocation;
	}

	/**
	 * Get cached file location
	 *
	 * @return string
	 */
	public function getCachedFileLocation() {
		return $this->cachedFileLocation;
	}

	/**
	 * Set key
	 *
	 * @param $key
	 */
	private function setKey($key) {
		$this->key = $key;
	}

	/**
	 * Get key
	 *
	 * @return null
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * Set cache location
	 *
	 * @param $cacheLocation
	 */
	private function setCacheLocation($cacheLocation) {
		$this->cacheLocation = $cacheLocation;
	}

	/**
	 * Get cache location
	 *
	 * @return string
	 */
	private function getCacheLocation() {
		return $this->cacheLocation;
	}

	/**
	 * Set loaded from cache
	 * Enforce boolean
	 *
	 * @param $loadedFromCache
	 */
	private function setLoadedFromCache($loadedFromCache) {
		if ($loadedFromCache === false) {
			$this->loadedFromCache = $loadedFromCache;
		}
		else {
			$this->loadedFromCache = true;
		}
	}
}


