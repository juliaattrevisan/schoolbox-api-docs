<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace SocialStream\Cache;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use SocialStream\PostMapper;
use SocialStream\UrlDetection;
use SocialStream_Post_Abstract;

/**
 * Helper for loading the `cacheHash` property of a `SocialStream_Post_Abstract`
 * object.
 *
 * Intended to replace `\SocialStream\Cache\Original`.
 */
class CacheHashLoader
{
    public function __construct(
        private ClientInterface $client,
        private PostMapper $postMapper,
        private StorageMapper $storageMapper,
    ) {
    }

    public function load(SocialStream_Post_Abstract $post): void
    {
        if ($post->getCacheHash()) {
            return;
        }

        $this->saveStreamContents($post);
    }

    /**
     * Save stream contents from the url.
     *
     * @return bool Was the post changed?
     */
    private function saveStreamContents(SocialStream_Post_Abstract $post): bool
    {
        $url = $post->getThumbnail();
        if (!isset($url)) {
            return false;
        }

        $hash         = null;
        $urlDetection = new UrlDetection($post->getThumbnail());
        if ($urlDetection->isLocalUrl()) {
            if ($urlDetection->isStorageUrl()) {
                // it's in storage - just grab the hash and be done with it
                $hash = $urlDetection->getHash();
            }
        } else {
            // Load the thumbnail from the HTTP client, and save it into storage
            try {
                $response = $this->client->request('GET', $url);
            } catch (GuzzleException $e) {
                // Don't try to set anything?
                return false;
            }

            // Remove any query string from the url,
            // but only after we've retrieved it in case some parts of the query
            // were necessary.
            $baseName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
            $storage  = $this->storageMapper->acceptStream($baseName, $response->getBody()->getContents(), true);
            $hash     = $storage->getHash();
        }

        // Only save post it if it actually already exists
        if ($hash && $post->getPostId()) {
            $this->postMapper->suppressNotification();
            try {
                $this->postMapper->save($post);
                return true;
            } finally {
                $this->postMapper->restoreNotification();
            }
        }
        return false;
    }


    /**
     * Process url using fopen in the usual way
     *
     * @param string $url
     * @return bool
     * @throws \PDOException
     * @throws \Schoolbox\File\Exception
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function processUrl($url): bool
    {
        $streamHandle = @fopen($url, 'r');

        if ($streamHandle) {
            // Remove any query string from the url,
            // but only after we've retrieved it in case some parts of the query were necessary
            $baseName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);

            $smapper = Storage\Mapper::getInstance();
            $storage = $smapper->acceptStream($baseName, $streamHandle, true);

            $this->post->setCacheHash($storage->getHash());

            // only save it if it actually already exists
            if ($this->post->getPostId()) {
                $postMapper = PostMapper::getInstance();
                $postMapper->suppressNotification();
                $postMapper->save($this->post);
                $postMapper->restoreNotification();
            }

            $this->file = $storage;

            return true;
        }

        return false;
    }
}
