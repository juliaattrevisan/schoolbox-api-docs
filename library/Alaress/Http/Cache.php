<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Http;

use GuzzleHttp\ClientInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * HTTP client, with a cache wrapped around it: designed for caching the
 * response from HTTP requests.
 */
class Cache implements CacheInterface
{
    /**
     * Constructor
     *
     * @param ClientInterface $httpClient      Client used for actually making HTTP requests.
     * @param CacheInterface  $internalCache   Cache implementation used to actually store responses.
     * @param int             $defaultLifetime Default lifetime of cache keys.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private CacheInterface $internalCache,
        private int $defaultLifetime,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function get($key, $default = null)
    {
        $result = $this->internalCache->get($key, $default);
        if ($result === $default) {
            return $result;
        }

        // Load result from HTTP
        $response = $this->httpClient->request('GET', $key);
        return $response->getBody()->getContents();
    }

    /**
     * @inheritdoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        return $this->internalCache->set($key, $value, $ttl ?? $this->defaultLifetime);
    }

    /**
     * @inheritdoc
     */
    public function delete($key): bool
    {
        return $this->internalCache->delete($key);
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        return $this->internalCache->clear();
    }

    /**
     * @inheritdoc
     */
    public function getMultiple($keys, $default = null): iterable
    {
        return $this->internalCache->getMultiple($keys, $default);
    }

    /**
     * @inheritdoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        return $this->internalCache->setMultiple($values, $ttl);
    }

    /**
     * @inheritdoc
     */
    public function deleteMultiple($keys): bool
    {
        return $this->internalCache->deleteMultiple($keys);
    }

    /**
     * @inheritdoc
     */
    public function has($key): bool
    {
        return $this->internalCache->has($key);
    }
}
