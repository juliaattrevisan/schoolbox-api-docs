<?php


interface Alaress_Image_Cache_Interface {
	/**
	 * Loads an image somehow.
	 *
	 * @return array
	 */
	public function load();

	/**
	 * Find out whether we loaded from cache or not
	 *
	 * @return bool
	 */
	public function isLoadedFromCache();

	/**
	 * Get cache key for this item.
	 *
	 * @return string
	 */
	public function getKey();

	/**
	 * Get cache file location.
	 *
	 * @return string
	 */
	public function getCachedFileLocation();
}
