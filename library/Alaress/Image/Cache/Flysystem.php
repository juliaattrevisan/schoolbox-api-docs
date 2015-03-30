<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Image\Cache;


use League\Flysystem\File;
use League\Flysystem\FilesystemInterface;

class Flysystem implements CacheInterface
{

    /**
     * The actual resource object from storage
     *
     * @var File
     */
    protected $resource;

    /** @var string */
    protected $truePath;

    /** @var FilesystemInterface */
    protected $cacheFs;

    /**
     * The $key is a way to uniquely associate a cached image with a particular
     * series of transformations applied to it. In this caching implementation,
     * it's the file hash concatenated with the name of the transformation applied.
     *
     * e.g. e2685ab-square64
     *
     * @var string
     */
    protected $key = null;

    /**
     * Remember whether we loaded from the cached file. If it was loaded from the
     * cache, we can skip transformations as we assume they have been done in the past.
     * Otherwise we would be re-transforming an already-transformed image repeatedly
     *
     * @var bool
     */
    protected $loadedFromCache = false;


    /**
     * Constructor
     * Remembers what image you are trying to load from cache
     *
     * @param File       $resource
     * @param string     $key
     * @param FilesystemInterface $cacheFs
     */
    public function __construct(File $resource, $key, FilesystemInterface $cacheFs)
    {
        $this->resource = $resource;
        $this->cacheFs  = $cacheFs;
        $this->setKey($key);
    }

    /**
     * Set key
     *
     * @param string $key
     */
    protected function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * Get key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set loaded from cache
     * Enforce boolean
     *
     * @param $loadedFromCache
     */
    protected function setLoadedFromCache($loadedFromCache)
    {
        if ($loadedFromCache === false) {
            $this->loadedFromCache = $loadedFromCache;
        } else {
            $this->loadedFromCache = true;
        }
    }

    /**
     * Find out whether we loaded from cache or not
     *
     * @return bool
     */
    public function isLoadedFromCache()
    {
        return $this->loadedFromCache;
    }

    /**
     * Load an image by an id
     *
     * @return File
     */
    public function load()
    {
        $cached = $this->loadCache();

        /* If this particular transformation key existed at some point,
         * this is what it's name would be
         *
         * e.g. e2685ab-Web, e2685ab-ChickenPox, etc. where Web and ChickenPox
         * correspond to a series of transformations
         */
        if ($cached->exists()) {
            // flag that this is an already-cached file
            $this->setLoadedFromCache(true);
            return $cached;
        }

        /* Otherwise continue with this cache implementation
         */
        return $this->resource;
    }


    /**
     * @param File $file
     */
    protected function makeCache(File $file)
    {
        $file->setFilesystem($this->cacheFs);
        $file->setPath($file->getPath() . '-' . $this->getKey());
    }

    /**
     * @return File
     */
    public function loadCache()
    {
        $cached = clone $this->resource;

        $this->makeCache($cached);

        return $cached;
    }
}