<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\CacheFactory;

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
     * Platform float size: must be calculated at runtime, because no equivalent
     * of PHP_INT_SIZE exists.
     */
    private int $floatSize;

    /**
     * Constructor
     *
     * @param CacheInterface $cache     The cache object to wrap around.
     * @param string         $namespace The cache's namespace, will be prefixed to keys in the description.
     * @param Tracer|null    $tracer    The tracer object to use to record spans: if not set, use the default tracer.
     */
    public function __construct(
        private CacheInterface $cache,
        private string $namespace,
        ?Tracer $tracer = null,
    ) {
        $this->tracer    = $tracer ?? Tracer::getDefaultTrace();
        $this->floatSize = strlen(pack('d', 0));
    }

    /**
     * @inheritdoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->get($key, $default),
            $this->generateDescription($key),
            'cache.get',
            'auto.cache',
            $this->generateData(key: $key),
            fn (mixed $result) => [
                'cache.hit'       => $result !== $default,
                'cache.item_size' => $this->calculateItemSize($result),
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
            $this->generateDescription($key),
            'cache.put',
            'auto.cache',
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
            $this->generateDescription($key),
            'cache.remove',
            'auto.cache',
            $this->generateData(key: $key),
            fn (bool $result) => [
                'cache.success' => $result,
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
            'auto.cache',
            $this->generateData(),
            fn (bool $result) => [
                'cache.success' => $result,
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
        $keysList = iterator_to_array($keys);
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->getMultiple($keysList, $default),
            $this->generateDescriptionForKeys($keysList),
            'cache.getMultiple',
            'auto.cache',
            $this->generateData(),
        );
    }

    /**
     * @inheritdoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $valuesList = iterator_to_array($values);
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->setMultiple($valuesList, $ttl),
            $this->generateDescriptionForKeys(array_keys($valuesList)),
            'cache.putMultiple',
            'auto.cache',
            $this->generateData(),
        );
    }

    /**
     * @inheritdoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $keysList = iterator_to_array($keys);
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->deleteMultiple($keys),
            $this->generateDescriptionForKeys($keysList),
            'cache.removeMultiple',
            'auto.cache',
            $this->generateData(),
        );
    }

    /**
     * @inheritdoc
     */
    public function has(string $key): bool
    {
        return $this->tracer->runWithinSpan(
            fn () => $this->cache->has($key),
            $this->generateDescription($key),
            'cache.has',
            'auto.cache',
            $this->generateData(key: $key),
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
            $data['cache.key'] = $this->generateDescription($key);
        }
        if (isset($value)) {
            $data['cache.item_size'] = $this->calculateItemSize($value);
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
        // a TTL can't be assumed:
        // this is because the number of days in a month can only be guessed,
        // as can the number of hours in a day (due to DST switchover).
        // It'll be up to `$this->cache` to determine the actual TTL of the
        // cache item.
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

    /**
     * Generate a description from the cache's namespace, and the requested key.
     *
     * @param string $key
     * @return string
     */
    private function generateDescription(string $key): string
    {
        return sprintf('%s:%s', $this->namespace, $key);
    }

    /**
     * Generate a description from the cache's namespace, and a list of
     * requested keys.
     *
     * @param array $keys
     * @return string
     */
    private function generateDescriptionForKeys(array $keys): string
    {
        return implode(
            ',',
            array_map(
                $this->generateDescription(...),
                array_map(strval(...), $keys),
            ),
        );
    }

    /**
     * Calculate the size of `$value`, as fetched/stored in the cache.
     *
     * @param mixed $value
     * @return int|null The size of `$value`, or `null` if it's an object or
     *                  array and thus can't be realistically calculated.
     */
    private function calculateItemSize(mixed $value): ?int
    {
        if (is_string($value)) {
            return strlen($value);
        } elseif (is_int($value)) {
            return PHP_INT_SIZE;
        } elseif (is_float($value)) {
            return $this->floatSize;
        } elseif (is_bool($value)) {
            return 1;
        }
        return null;
    }
}
