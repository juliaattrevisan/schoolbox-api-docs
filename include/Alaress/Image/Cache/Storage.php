<?php


class Alaress_Image_Cache_Storage {

    /* The image to load
      */
    private $imageId = null;

    /* The actual resource object from storage */

    private $resource;

    /* The $key is a way to uniquely associate a cached image with a particular
      * series of transformations applied to it. In this caching implementation,
      * it's the fileID concatenated with the name of the transformation applied.
      *
      * e.g. 51-Web.dat
      */
    private $key = null;

    /* This is where original images are stored
      */
    private $originalsLocation = '';

    /* This is where cached images are stored
      */
    private $cacheLocation = '';

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
     * @param $imageId
     * @param $key
     */
    public function __construct($resource, $key) {

        $this->resource = $resource;
        $this->setImageId($resource->id);
        $this->setKey($key);
        $this->setCacheLocation(Cfg::get('stream_dir').'/storage');
    }

    /**
     * Load an image by an id
     *
     * @return mixed
     */
    public function load() {

        /* If this particular transformation key existed at some point,
           * this is what it's name would be
           *
           * e.g. 51-Web.dat, 51-ChickenPox.dat, etc. where Web and ChickenPox
           * correspond to a series of transformations
           */
        $cachedFilename = $this->getImageId() . '-' . $this->getKey() . '.dat';

        /* This is where you'd find it in the filesystem if it existed previously
           */
        $this->setCachedFileLocation($this->getCacheLocation() . '/' . $cachedFilename);

        /* If it exists in the cache, just use the Filesystem cache method instead
           * as it understands how to load directly from the filesystem
           */
        if (file_exists($this->getCachedFileLocation())) {
            $this->setLoadedFromCache(true);

            return $this->loadPreviouslyCachedFile($this->getCachedFileLocation());
        }

        /* Otherwise continue with this cache implementation
           */
        return $this->standardise();
    }

    /**
     * If it was found in the cache, load this one instead of trying to make a new one
     *
     * @param $cachedFileLocation
     * @return mixed
     */
    private function loadPreviouslyCachedFile($cachedFileLocation) {
        $alternateCache = new Alaress_Image_Cache_Filesystem($cachedFileLocation, $this->getKey());

        return $alternateCache->load();
    }

    /**
     * Standard temporary interface
     *
     * @param $imageArray
     * @return array
     */
    public function standardise() {
        return array(
            'fileID' => $this->resource->id,
            'filepath' => $this->resource->getFileLocation(),
            'filename' => $this->resource->filename,
            'mime' => $this->resource->mime,
            'size' => $this->resource->filesize,
        );

    }

    /* Getters/Setters
      */

    /**
     * Set image id
     *
     * @param $imageId
     */
    private function setImageId($imageId) {
        $this->imageId = $imageId;
    }

    /**
     * Get image id
     *
     * @return null
     */
    private function getImageId() {
        return $this->imageId;
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
     * Set loaded from cache
     * Enforce boolean
     *
     * @param $loadedFromCache
     */
    private function setLoadedFromCache($loadedFromCache) {
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
    public function isLoadedFromCache() {
        return $this->loadedFromCache;
    }
}
