<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Schoolbox\Url\Uri;

/**
 * HTTP client, with a cache wrapped around it: designed for caching the
 * response from HTTP requests.
 *
 * Designed to replace `Alaress_Cache_Abstract`.
 */
class Cache
{
    /**
     * Request timeout: how long should this cache's HTTP client wait for a
     * request to complete?
     */
    private const REQUEST_TIMEOUT = 20;

    /**
     * Connection timeout: how long should this cache's HTTP client wait to
     * establish a connection?
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * Default header for HTTP requests.
     *
     * (This is the original user agent header from `Alaress_Cache_Abstract`.)
     */
    private const DEFAULT_HEADERS = [
        'User-Agent' => 'Mozilla/5.0 (Schoolbox)'
    ];

    /**
     * Constructor
     *
     * @param ClientInterface $httpClient      Client used for actually making HTTP requests.
     * @param CacheInterface  $internalCache   Cache implementation used to actually store responses.
     * @param int|null        $defaultLifetime Default lifetime of cache keys, or null for no expiry.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private CacheInterface $internalCache,
        private int|null $defaultLifetime,
        private int $requestTimeout = self::REQUEST_TIMEOUT,
        private int $connectTimeout = self::CONNECT_TIMEOUT,
    ) {
    }

    /**
     * Retrieve the response body of a URL from the cache: on miss, requests the
     * URL via the HTTP client, and caches the response body.
     *
     * @param string      $url     The URL to retrieve.
     * @param string|null $key     If provided: use this as the cache key,
     *                             otherwise derive from `$url`.
     * @param array       $headers Any headers to pass to the request.
     * @param int|null    $ttl     If provided: cache for this amount of time,
     *                             otherwise use `$this->defaultLifetime`.
     *
     * @return string
     * @throws InvalidArgumentException if the cache key is somehow not valid
     * @throws GuzzleException          if the HTTP request failed
     * @phpstan-ignore-next-line `\Psr\SimpleCache\InvalidArgumentException`
     *                            doesn't implement `Throwable`, but its
     *                            implementations should
     */
    public function request(string $url, ?string $key = null, array $headers = [], ?int $ttl = null): string
    {
        // Attempt to load cached response
        $key    ??= $this->hashKey($url);
        $result = $this->internalCache->get($key);
        if (isset($result)) {
            return $result;
        }

        // Otherwise, load response from HTTP client

        // User agent header from `Alaress_Cache_Abstract`:
        // note that this implementation allows the UA to be overridden from
        // outside
        $headers = array_merge(self::DEFAULT_HEADERS, $headers);

        // From SA-13472:
        // Check scheme: if webcal, update the scheme to HTTP, as CURL
        // (which HTTP client uses internally) doesn't understand that scheme.
        // Hopefully the remote server will just redirect to HTTPS?
        $url = new Uri($url);
        if ('webcal' === $url->getScheme()) {
            $url = $url->withScheme('http');
        }

        // Make the request and store it
        // TODO lock mechanism? if lock is present, wait until it times out?
        // TODO ensure that base URL is the FQDN of the instance
        $response     = $this->httpClient->request(
            'GET',
            $url,
            [
                RequestOptions::HEADERS         => $headers,
                RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
                RequestOptions::TIMEOUT         => $this->requestTimeout,
            ]
        );
        $responseBody = $response->getBody()->getContents();
        $this->internalCache->set($key, $responseBody, $ttl ?? $this->defaultLifetime);
        return $responseBody;
    }

    /**
     * Invalidates a cached value stored as part of `$this->request`.
     *
     * @param string      $url
     * @param string|null $key
     * @return void
     * @throws InvalidArgumentException
     * @phpstan-ignore-next-line `\Psr\SimpleCache\InvalidArgumentException`
     *                           doesn't implement `Throwable`, but its
     *                           implementations should
     */
    public function invalidate(string $url, ?string $key = null): void
    {
        $this->internalCache->delete($key ?? $this->hashKey($url));
    }

    /**
     * Hash the key (in this case, a URL) before working with it.
     *
     * @param string $key
     * @return string
     */
    private function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }
}
