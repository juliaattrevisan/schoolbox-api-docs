<?php
/**
 * Defines an interface for image caching.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Image\Cache;

use League\Flysystem as Fly;

interface CacheInterface
{
    /**
     * Loads an image somehow.
     *
     * @return Fly\File
     */
    public function load();


    /**
     * Loads the cached version of the file, even if absent
     *
     * @return Fly\File
     */
    public function loadCache();

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

}
