<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Http\Test;

use Alaress\Http\Cache;
use Cache\Bridge\SimpleCache\SimpleCacheBridge;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Schoolbox\Url\Uri;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Test for cache.
 */
#[CoversClass(Cache::class)]
class CacheTest extends TestCase
{
    /**
     * Test that absolute URLs are cached.
     *
     * @return void
     */
    public function testAbsoluteRequest(): void
    {
        $internalCache = new SimpleCacheBridge(new ArrayAdapter());
        $mockClient    = $this->createMock(Client::class);
        $mockClient->method('request')
            ->with('GET', 'https://schoolbox.education/test.php')
            ->willReturn(new Response(200, [], 'test'));
        $cache = new Cache(
            $mockClient,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
        );

        $this->assertEquals('test', $cache->request('https://schoolbox.education/test.php'));
    }

    /**
     * Test that relative URLs are evaluated according to the cache's base URL.
     *
     * @return void
     */
    public function testRelativeRequest(): void
    {
        $internalCache = new SimpleCacheBridge(new ArrayAdapter());
        $mockClient    = $this->createMock(Client::class);
        $mockClient->method('request')
            ->with('GET', 'https://vagrant.dev.schoolbox.cloud/test.php')
            ->willReturn(new Response(200, [], 'test'));
        $cache = new Cache(
            $mockClient,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
        );

        $this->assertEquals('test', $cache->request('/test.php'));
    }

    /**
     * Test that cache expiry is respected.
     *
     * @return void
     */
    public function testCacheExpiry(): void
    {
        $result1       = 'test';
        $result2       = 'test2';
        $internalCache = new SimpleCacheBridge(new ArrayAdapter());
        $mockClient    = $this->createMock(Client::class);
        $mockClient->method('request')
            ->willReturn(new Response(200, [], $result1));
        $cache = new Cache(
            $mockClient,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
        );

        $this->assertEquals($result1, $cache->request('/test.php', ttl: 2));

        // Test what happens before the cache expires
        sleep(1);
        $mockClient = $this->createMock(Client::class);
        $mockClient->method('request')
            ->willReturn(new Response(200, [], $result2));
        $cache = new Cache(
            $mockClient,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
        );
        $this->assertEquals($result1, $cache->request('/test.php', ttl: 2));

        // Test what happens after the cache expires
        sleep(2);
        $this->assertEquals($result2, $cache->request('/test.php', ttl: 2));
    }

    /**
     * Test lock is respected, by:
     * * preventing lock keys from being deleted from the cache
     * * making two requests with two separate clients for the same cache
     * * ensuring that the second request has been held up long enough for the
     *   lock to expire
     *
     * @return void
     */
    public function testLockExpiry(): void
    {
        $result1 = 'test';
        $result2 = 'test2';

        // Custom cache which doesn't delete locks
        $internalCache = new class (new ArrayAdapter()) extends SimpleCacheBridge {
            public function delete($key)
            {
                if (str_contains($key, 'lock')) {
                    return true;
                }
                return parent::delete($key);
            }
        };

        $requestTimeout = 1;
        $connectTimeout = 1;
        $lockTimeout    = $requestTimeout + $connectTimeout;
        $mockClient1 = $this->createMock(Client::class);
        $mockClient1->method('request')
            ->willReturn(new Response(200, [], $result1));
        $cache1 = new Cache(
            $mockClient1,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
            $requestTimeout,
            $connectTimeout,
        );

        $mockClient2 = $this->createMock(Client::class);
        $mockClient2->method('request')
            ->willReturn(new Response(200, [], $result2));
        $cache2 = new Cache(
            $mockClient2,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
            $requestTimeout,
            $connectTimeout,
        );

        // Make the two requests:
        // verify that I'm kept waiting about 2s (request + connect timeout)
        // between the two.
        // Note that the cache class works only at second-level accuracy, thus
        // it is not possible to verify the wait at any higher resolution than
        // one second.
        $this->assertEquals($result1, $cache1->request('/test.php', ttl: 1));
        $time          = microtime(true);
        $result2Actual = $cache2->request('/test.php', ttl: 1);
        $time          = microtime(true) - $time;
        $this->assertEquals($result2, $result2Actual);
        $this->assertEqualsWithDelta($lockTimeout, $time, 1);
    }

    /**
     * Test invalidation of cache.
     *
     * @return void
     */
    public function testInvalidate(): void
    {
        $result1       = 'test';
        $result2       = 'test2';
        $internalCache = new SimpleCacheBridge(new ArrayAdapter());
        $mockClient    = $this->createMock(Client::class);
        $mockClient->method('request')
            ->with('GET', 'https://vagrant.dev.schoolbox.cloud/test.php')
            ->willReturn(new Response(200, [], $result1));
        $cache = new Cache(
            $mockClient,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
        );

        $this->assertEquals($result1, $cache->request('/test.php', ttl: 100));
        $cache->invalidate('/test.php');

        $mockClient = $this->createMock(Client::class);
        $mockClient->method('request')
            ->with('GET', 'https://vagrant.dev.schoolbox.cloud/test.php')
            ->willReturn(new Response(200, [], $result2));
        $cache = new Cache(
            $mockClient,
            $internalCache,
            null,
            new Uri('https://vagrant.dev.schoolbox.cloud'),
        );

        // Test what happens after the cache expires
        $this->assertEquals($result2, $cache->request('/test.php', ttl: 100));
    }
}
