<?php
/**
 * Social stream cache image fetch
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
     * Hook method
     *
     * @return mixed|void
     */
    protected function setup()
    {
        $this->cachedTime = \Cfg::get('xml_cache_timeout');
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
            return \Cfg::get('file_dir') . '/' . $urlDetection->getId() . '.dat';
        }

        return \Cfg::get('stream_dir') . '/socialstream/' . $this->cacheFilePrefix . '_' . $this->encode($this->url);
    }


    /**
     * @return void
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     */
    protected function retrieveStreamContents(): void
    {
        // run cache detection
        if ($this->post->getCacheHash()) {
            $file = Storage\Mapper::getInstance()->findByHash($this->post->getCacheHash());
            $this->cachedFile = $file;

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
     * @throws \UnexpectedValueException
     */
    protected function saveStreamContents(): bool
    {
        $urlDetection = new \SocialStream\UrlDetection($this->url);

        if ($urlDetection->isLocalUrl()) {

            if ($urlDetection->isStorageUrl()) {
                // it's in storage - just grab the hash and be done with it
                $this->cachedFile = Storage\Mapper::getInstance()->findByHash($urlDetection->getHash());
            }
        } else {
            $url = $this->url;
            $this->setResult($this->processUrl($url));
        }

        return true;
    }


    /**
     * Process url using fopen in the usual way
     *
     * @param $url
     * @return bool
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function processUrl($url)
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

            $this->cachedFile = $storage;

            return true;
        }

        return false;
    }

    /**
     * Has thumbnail is either when file has successfully downloaded or the cache file already exists
     */
    public function hasThumbnail()
    {
        return $this->getResult() || $this->cachedFile instanceof Storage\Model;
    }

    /* Getters/Setters
     */

    /**
     * Set result
     *
     * @param boolean $result
     */
    protected function setResult($result)
    {
        $this->result = $result;
    }

    /**
     * Get result
     *
     * @return boolean
     */
    protected function getResult()
    {
        return $this->result;
    }
}
