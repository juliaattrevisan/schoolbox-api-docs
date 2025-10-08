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
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Random\RandomException;
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
     * Prefix for the lock key within the cache.
     *
     * If the requested URL is in cache but the lock is, another process is
     * requesting the cached URL: wait for that request to complete.
     */
    private const LOCK_KEY_PREFIX = 'lock__';

    /**
     * The base exponential backoff value to wait for the lock to expire, in
     * microseconds.
     */
    private const BASE_JITTER = 1_000_000;

    /**
     * How many iterations to wait for the lock to expire before giving up?
     */
    private const MAX_ITERATIONS = 20;

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
     * @param UriInterface    $baseUrl         The base URL of this Schoolbox instance: if a relative request is made,
     *                                         it will be made from this base URL.
     * @param int             $requestTimeout  The max amount of time the request is allowed to take.
     * @param int             $connectTimeout  The max amount of time to wait when connecting to the server.
     */
    public function __construct(
        private ClientInterface $httpClient,
        private CacheInterface $internalCache,
        private int|null $defaultLifetime,
        private UriInterface $baseUrl,
        private int $requestTimeout = self::REQUEST_TIMEOUT,
        private int $connectTimeout = self::CONNECT_TIMEOUT,
    ) {
    }

    /**
     * Retrieve the response body of a URL from the cache: on miss, requests the
     * URL via the HTTP client, and caches the response body.
     *
     * @param string   $url     The URL to retrieve.
     * @param string   $prefix  Prefix to apply to the cache key.
     *                          (The main key is always derived from `$url`.)
     * @param array    $headers Any headers to pass to the request.
     * @param int|null $ttl     If provided: cache for this amount of time,
     *                          otherwise use `$this->defaultLifetime`.
     *
     * @return string
     * @throws InvalidArgumentException if the cache key is somehow not valid
     * @throws GuzzleException          if the HTTP request failed
     */
    public function request(string $url, string $prefix = '', array $headers = [], ?int $ttl = null): string
    {
        // Make `$url` absolute if it isn't already
        $url = $this->makeAbsolute($url);

        // Attempt to load cached response
        $key     = $prefix . $this->hashKey($url);
        $lockKey = self::LOCK_KEY_PREFIX . $key;
        $maxWait = $this->connectTimeout + $this->requestTimeout;

        // Is there a lock in place? If so, wait for it to expire before
        // attempting to fetch.
        $this->waitForLockToExpire($lockKey, microtime(true) + $maxWait);

        // Return cached result if there
        $result = $this->internalCache->get($key);
        if (isset($result)) {
            // If somehow this isn't a string: cast it to one
            if (!is_string($result)) {
                trigger_error(
                    'Internal cache returned a non-string value: casting to string and returning',
                    E_USER_WARNING,
                );
                return (string)$result;
            }
            return $result;
        }

        // Otherwise, load response from HTTP client: also lock this fetch so
        // that other attempts are not waiting.
        $this->internalCache->set($lockKey, '', $maxWait);

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

        // Make the request and store it, clearing the lock in the end
        try {
            $response = $this->httpClient->request(
                'GET',
                $url,
                [
                    RequestOptions::HEADERS         => $headers,
                    RequestOptions::CONNECT_TIMEOUT => $this->connectTimeout,
                    RequestOptions::TIMEOUT         => $this->requestTimeout,
                ]
            );
        } finally {
            $this->internalCache->delete($lockKey);
        }
        $responseBody = $response->getBody()->getContents();
        try {
            $this->internalCache->set($key, $responseBody, $ttl ?? $this->defaultLifetime);
        } catch (\TypeError $e) {
            // If the value is not serializable, we can't cache it.
            // This is a safeguard against non-serializable values being cached.
            // This may have also been it cached the entry but for a timeout of 0, so it fetched null.
            trigger_error(
                'Failed to cache response, (Possibly cache timeout set to 0). Error: ' . $e->getMessage(),
                E_USER_WARNING,
            );
        }

        return $responseBody;
    }

    /**
     * Invalidates a cached value stored as part of `$this->request`.
     *
     * @param string $url
     * @param string $prefix
     * @return void
     * @throws InvalidArgumentException
     */
    public function invalidate(string $url, string $prefix = ''): void
    {
        $url     = $this->makeAbsolute($url);
        $key     = $prefix . $this->hashKey($url);
        $lockKey = self::LOCK_KEY_PREFIX . $key;
        $this->internalCache->deleteMultiple([$key, $lockKey]);
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

    /**
     * Wait for the lock to expire.
     *
     * This checks if the lock is there: if it is, wait using exponential
     * backoff until it is not there, or until `$waitUntil` is reached.
     *
     * @param string $lockKey
     * @param float  $waitUntil
     * @param int    $iteration
     * @return void
     * @throws InvalidArgumentException
     */
    private function waitForLockToExpire(string $lockKey, float $waitUntil, int $iteration = 1): void
    {
        // Short-circuit if too many iterations
        if ($iteration >= self::MAX_ITERATIONS) {
            return;
        }

        // Short-circuit if `$waitUntil` is reached already
        $now                        = microtime(true);
        $microsecondsUntilWaitUntil = (int)(($waitUntil - $now) * 1_000_000);
        if ($microsecondsUntilWaitUntil <= 0) {
            return;
        }

        // If there's no lock, return immediately
        if (!$this->internalCache->has($lockKey)) {
            return;
        }

        // Sleep until first of $waitUntil or ($now + $jitter)
        try {
            $jitterMicroseconds = random_int(0, self::BASE_JITTER * (2 ** $iteration));
        } catch (RandomException) {
            // In the unlikely event a random number can't be generated:
            // wait for a value in the middle
            $jitterMicroseconds = self::BASE_JITTER * (2 ** $iteration) / 2;
        }

        // Sleep until the expiry time has been reached:
        // need to recalculate time, as the lock wait is being measured to a
        // resolution of 0.1s in the test.
        $now                        = microtime(true);
        $microsecondsUntilWaitUntil = (int)(($waitUntil - $now) * 1_000_000);
        usleep(min($jitterMicroseconds, $microsecondsUntilWaitUntil));

        // And wait again, for longer this time
        $this->waitForLockToExpire($lockKey, $waitUntil, $iteration + 1);
    }

    /**
     * Make `$url` absolute to `$this->baseUrl`, if it isn't already absolute.
     *
     * @param string $url
     * @return string
     */
    private function makeAbsolute(string $url): string
    {
        if (str_starts_with($url, '/')) {
            $parts    = parse_url($url);
            $path     = $parts['path'] ?? '';
            $query    = $parts['query'] ?? '';
            $fragment = $parts['fragment'] ?? '';
            return $this->baseUrl->withPath($path)->withQuery($query)->withFragment($fragment)->__toString();
        }
        return $url;
    }
}
