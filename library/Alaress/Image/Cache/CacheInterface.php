<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Image\Cache;

use Schoolbox\Resource\Storage;

/**
 * Represents a cache overlay for a single file.
 */
interface CacheInterface
{
    /**
     * Loads an image somehow.
     *
     * @return Storage\Model
     */
    public function load(): Storage\Model;


    /**
     * Loads the cached version of the file, even if absent
     *
     * @return Storage\Model
     */
    public function loadCache(): Storage\Model;

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
