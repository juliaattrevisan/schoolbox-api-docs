<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace SocialStream\Cache;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Schoolbox\Resource\Storage\Receiver;
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
    /**
     * Constructor
     *
     * @param ClientInterface $client     For fetching the post's thumbnail.
     * @param PostMapper      $postMapper For saving the post.
     * @param Receiver        $receiver   For storing the cache hash to storage.
     */
    public function __construct(
        private ClientInterface $client,
        private PostMapper $postMapper,
        private Receiver $receiver,
    ) {
    }

    /**
     * Load the post's cache hash, and the thumbnail into storage.
     *
     * @param SocialStream_Post_Abstract $post
     * @return void
     */
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
        // Only save post it if it actually already exists?
        // Not sure why this stipulation existed, but it did in
        // `\SocialStream\Cache\Original`.
        // (Possibly because the post is saved before the content is fully set,
        //  and don't want to raise a notification from a partial post?)
        if (!$post->getPostId()) {
            return false;
        }

        // No thumbnail? exit early
        $thumbnail = $post->getThumbnail();
        if (!isset($thumbnail)) {
            return false;
        }

        $save         = false;
        $urlDetection = new UrlDetection($thumbnail);
        if ($urlDetection->isLocalUrl()) {
            if ($urlDetection->isStorageUrl()) {
                // it's in storage - just grab the hash and be done with it
                $post = $post->setCacheHash($urlDetection->getHash());
                $save = true;
            }
        } else {
            // Load the thumbnail from the HTTP client, and save it into storage
            $response = null;
            try {
                $response = $this->client->request('GET', $thumbnail);
            } catch (GuzzleException $e) {
                // Could not download the thumbnail? Remove it if so.
                $post = $post->setCacheHash(null)->setThumbnail(null);
                $save = true;
            }

            if ($response) {
                // Remove any query string from the url for the storage name,
                // but only after we've retrieved it in case some parts of the
                // query were necessary.
                $baseName = pathinfo(parse_url($thumbnail, PHP_URL_PATH), PATHINFO_BASENAME);
                $storage  = $this->receiver->acceptStream($baseName, $response->getBody()->getContents(), true);
                $post     = $post->setCacheHash($storage->getHash());
                $save     = true;
            }
        }

        if ($save) {
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
}
