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

use Schoolbox\Resource\Storage;
use SocialStream\PostMapper;
use SocialStream_Post_Abstract;

class Original extends \Alaress_Cache_Abstract {

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
	protected function setup() {
		$this->setCachedTime(\Cfg::get('xml_cache_timeout'));
		$this->setLockTime($this->getLockTimeMultiplier() * $this->getCachedTime());
	}


    /**
     * @param SocialStream_Post_Abstract $post
     * @return self
     */
    public function initPost(SocialStream_Post_Abstract $post)
    {
        $this->setUrl($post->getThumbnail());

        $this->post = $post;

        $this->setLockFile($this->generateLockFilename());

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
	protected function generateCachedFilename() {
		$urlDetection = new \SocialStream\UrlDetection($this->getUrl());

		/* Find what the file was called
		 *
		 * Resource files would normally be copied into the socialstream cache location
		 * but to save space maybe we can redirect generateCachedFilename() instead,
		 * so it reads directly from the original's location.
		 */
		if ($urlDetection->isResourceUrl()) {
			return \Cfg::get('file_dir') . '/' . $urlDetection->getId() . '.dat';
		}

		return \Cfg::get('stream_dir') . '/socialstream/' . $this->getCacheFilePrefix() . '_' . $this->encode($this->getUrl());
	}


    /**
     * @return void
     */
    protected function retrieveStreamContents()
    {
        // run cache detection
        if ($this->post->getCacheHash()) {
            $file = Storage\Mapper::getInstance()->findByHash($this->post->getCacheHash());
            $this->setCachedFile($file);

        } else {
            $this->newCacheUpdate();
        }
    }

	/**
	 * Save stream contents from the url
	 *
	 * @return bool|void
	 */
	protected function saveStreamContents() {
		$urlDetection = new \SocialStream\UrlDetection($this->getUrl());

		if ($urlDetection->isLocalUrl()) {

			if ($urlDetection->isStorageUrl()) {
				// it's in storage - just grab the hash and be done with it
                $this->setCachedFile(Storage\Mapper::getInstance()->findByHash($urlDetection->getHash()));
			}
		}
		else {
			$url = $this->getUrl();
			$this->setResult($this->processUrl($url));
		}
	}

	/**
	 * Process url using fopen in the usual way
	 *
	 * @param $url
	 * @return bool
	 */
	private function processUrl($url) {
		$streamHandle = @fopen($url, 'r');

		if($streamHandle) {
            $smapper = Storage\Mapper::getInstance();
            $storage = $smapper->acceptStream(pathinfo($url, PATHINFO_BASENAME), $streamHandle, true);

            $this->post->setCacheHash($storage->getHash());

            // only save it if it actually already exists
            if ($this->post->getPostId()) {
                PostMapper::getInstance()->save($this->post);
            }

            $this->setCachedFile($storage);

            return true;
        }
        else {
		    return false;
        }
	}

	/**
	 * Has thumbnail is either when file has successfully downloaded or the cache file already exists
	 */
	public function hasThumbnail() {
		return $this->getResult() || $this->getCachedFile() instanceof Storage\Model;
	}

	/* Getters/Setters
	 */

	/**
	 * Set result
	 *
	 * @param boolean $result
	 */
	protected function setResult($result) {
		$this->result = $result;
	}

	/**
	 * Get result
	 *
	 * @return boolean
	 */
	protected function getResult() {
		return $this->result;
	}
}
