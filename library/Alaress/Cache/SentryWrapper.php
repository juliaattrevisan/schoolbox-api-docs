<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Cache;

use Alaress\Sentry\Tracing\Tracer;
use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Wrapper around a PSR-16 cache object, which creates Sentry traces for cache
 * hits and misses.
 *
 * @see https://develop.sentry.dev/sdk/telemetry/traces/modules/caches/
 */
class SentryWrapper implements CacheInterface
{
    /**
     * The tracer object to use to record spans.
     */
    private Tracer $tracer;

    /**
     * Constructor
     *
     * @param CacheInterface $cache  The cache object to wrap around.
     * @param Tracer|null    $tracer The tracer object to use to record spans:
     *                               if not set, use the default tracer.
     */
    public function __construct(private CacheInterface $cache, ?Tracer $tracer = null)
    {
        $this->tracer = $tracer ?? Tracer::getDefaultTrace();
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->get($key, $default),
            $key,
            'cache.get',
            'auto.cache.get',
            $this->generateData($key, null, null),
            fn (bool $result) => [
                'cache.hit'       => $result !== $default,
                'cache.item_size' => strlen($result),
                'cache.success'   => true,
            ],
            fn (Throwable $e) => [
                'cache.success' => false,
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->set($key, $value, $ttl),
            $key,
            'cache.put',
            'auto.cache.put',
            $this->generateData($key, $value, $ttl),
            fn (bool $result) => [
                'cache.write'   => $result,
                'cache.success' => $result,
            ],
            fn (Throwable $e) => [
                'cache.write'   => false,
                'cache.success' => false,
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function delete(string $key): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->delete($key),
            $key,
            'cache.remove',
            'auto.cache.remove',
            $this->generateData($key, null, null),
            fn (bool $result) => [
                'cache.success' => true,
            ],
            fn (Throwable $e) => [
                'cache.success' => false,
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->clear(),
            '',
            'cache.flush',
            'auto.cache.flush',
            [],
            fn (bool $result) => [
                'cache.success' => true,
            ],
            fn (Throwable $e) => [
                'cache.success' => false,
            ],
        );
    }

    /**
     * @inheritdoc
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->getMultiple($keys, $default),
            '',
            'cache.getMultiple',
            'auto.cache.getMultiple',
        );
    }

    /**
     * @inheritdoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->setMultiple($values, $ttl),
            '',
            'cache.putMultiple',
            'auto.cache.putMultiple',
        );
    }

    /**
     * @inheritdoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->deleteMultiple($keys),
            '',
            'cache.removeMultiple',
            'auto.cache.removeMultiple',
        );
    }

    /**
     * @inheritdoc
     */
    public function has(string $key): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->has($key),
            $key,
            'cache.has',
            'auto.cache.has',
            $this->generateData($key, null, null),
            fn (bool $result) => [
                'cache.hit'       => $result,
                'cache.success'   => true,
            ],
            fn (Throwable $e) => [
                'cache.success' => false,
            ],
        );
    }

    /**
     * Generates data for the span.
     *
     * @param string|null           $key
     * @param mixed                 $value
     * @param DateInterval|int|null $ttl
     * @return array
     */
    private function generateData(?string $key = null, mixed $value = null, DateInterval|int|null $ttl = null): array
    {
        $data = [];
        if (isset($key)) {
            $data['cache.key'] = $key;
        }
        if (isset($value)) {
            // Item size can only be determined if the value was a string
            $data['cache.item_size'] = strlen($value);
        }
        $dataTtl = $ttl;
        if ($dataTtl instanceof DateInterval) {
            $dataTtl = $this->convertDateIntervalToSeconds($dataTtl);
        }
        if (isset($dataTtl)) {
            $data['cache.ttl'] = $dataTtl;
        }
        return $data;
    }

    /**
     * Makes a best effort to convert a DateInterval to an int, for recording as
     * data for the cache's span.
     *
     * @param DateInterval $interval
     * @return int|null
     */
    private function convertDateIntervalToSeconds(DateInterval $interval): ?int
    {
        // Need to assume that if days, months, years or invert are set then
        // a TTL can't be assumed
        if ($interval->y || $interval->m || $interval->d || $interval->invert) {
            return null;
        }
        $seconds = 0;
        if ($interval->h) {
            $seconds += ($interval->h * 60 * 60);
        }
        if ($interval->i) {
            $seconds += ($interval->i * 60);
        }
        if ($interval->s) {
            $seconds += ($interval->s);
        }
        return $seconds;
    }
}
