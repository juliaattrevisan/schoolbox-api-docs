<?php
/**
 * Social stream cache image fetch
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream\Cache;

use Schoolbox\Resource\Storage;
use SocialStream\PostMapper;
use SocialStream_Post_Abstract;

class Original extends \Alaress_Cache_Abstract
{

    /**
     * Whether the given url was fopen'd successfully
     *
     * @var bool
     */
    protected $result = false;

    /** @var SocialStream_Post_Abstract */
    protected $post;

    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = 'socialstream_thumbnail';

    /**
     * A Storage instance for a thumbnail image
     *
     * These aren't stored in the filesystem like other cached items
     * such as tweeter streams, etc. they're stored in brownhole
     *
     * @var Storage\IStorage
     */
    private $file;

    /**
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup()
    {
        $this->cachedTime = \Config::get('xml_cache_timeout');
        $this->lockTime = $this->lockTimeMultiplier * $this->cachedTime;
    }


    /**
     * @param SocialStream_Post_Abstract $post
     * @return self
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function initPost(SocialStream_Post_Abstract $post): Original
    {
        $this->url = $post->getThumbnail();

        $this->post = $post;

        $this->lockFile = $this->generateLockFilename();

        /* Retrieve stream contents
        */
        $this->retrieveStreamContents();

        return $this;
    }

    /**
     * Figger out what the cached filename should be
     *
     * @return string
     */
    protected function generateCachedFilename(): string
    {
        $urlDetection = new \SocialStream\UrlDetection($this->url);

        /* Find what the file was called
         *
         * Resource files would normally be copied into the socialstream cache location
         * but to save space maybe we can redirect generateCachedFilename() instead,
         * so it reads directly from the original's location.
         */
        if ($urlDetection->isResourceUrl()) {
            return \Config::get('file_dir') . '/' . $urlDetection->getId() . '.dat';
        }

        return \Config::get('stream_dir') . '/socialstream/' . $this->cacheFilePrefix . '_' . $this->encode($this->url);
    }


    /**
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function retrieveStreamContents(): void
    {
        // run cache detection
        if ($this->post->getCacheHash()) {
            $this->file = Storage\Mapper::getInstance()->findByHash($this->post->getCacheHash());

        } else {
            $this->newCacheUpdate();
        }
    }


    /**
     * Save stream contents from the url
     *
     * @return bool
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function saveStreamContents(): bool
    {
        $urlDetection = new \SocialStream\UrlDetection($this->url);

        if ($urlDetection->isLocalUrl()) {

            if ($urlDetection->isStorageUrl()) {
                // it's in storage - just grab the hash and be done with it
                $this->file = Storage\Mapper::getInstance()->findByHash($urlDetection->getHash());
            }
        } else {
            $url = $this->url;
            $this->result = $this->processUrl($url);
        }

        return true;
    }


    /**
     * Process url using fopen in the usual way
     *
     * @param $url
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     */
    private function processUrl($url): bool
    {
        $streamHandle = @fopen($url, 'r');

        if ($streamHandle) {
            $smapper = Storage\Mapper::getInstance();
            $storage = $smapper->acceptStream(pathinfo($url, PATHINFO_BASENAME), $streamHandle, true);

            $this->post->setCacheHash($storage->getHash());

            // only save it if it actually already exists
            if ($this->post->getPostId()) {
                PostMapper::getInstance()->save($this->post);
            }

            $this->file = $storage;

            return true;
        }

        return false;
    }


    /**
     * Get the storage instance representing this file
     *
     * @return Storage\IStorage
     */
    public function getFile(): Storage\IStorage
    {
        return $this->file;
    }

    /**
     * Has thumbnail is either when file has successfully downloaded or the cache file already exists
     */
    public function hasThumbnail()
    {
        return $this->result || $this->file instanceof Storage\Model;
    }
}
