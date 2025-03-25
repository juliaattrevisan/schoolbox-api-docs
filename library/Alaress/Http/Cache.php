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
        $response     = $this->httpClient->request('GET', $url, [RequestOptions::HEADERS => $headers]);
        $responseBody = $response->getBody()->getContents();
        $this->internalCache->set($key, $responseBody, $ttl ?? $this->defaultLifetime);
        return $responseBody;
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
