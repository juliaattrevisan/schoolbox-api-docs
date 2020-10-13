<?php
/**
 * Defines an interface for image caching.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
    public function load(): Fly\File;


    /**
     * Loads the cached version of the file, even if absent
     *
     * @return Fly\File
     */
    public function loadCache(): Fly\File;

    /**
     * Find out whether we loaded from cache or not
     *
     * @return bool
     */
    public function isLoadedFromCache(): bool;

    /**
     * Get cache key for this item.
     *
     * @return string
     */
    public function getKey(): string;
}
