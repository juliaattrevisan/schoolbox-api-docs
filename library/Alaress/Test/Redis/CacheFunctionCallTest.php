<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Test\Redis;

use Alaress\Redis;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

#[CoversMethod(Redis::class, 'cacheFunctionCall')]
class CacheFunctionCallTest extends TestCase
{
    #[DataProvider('getTestCacheFunctionCallData')]
    public function testCacheFunctionCallCompression(
        mixed $expected,
        ?string $cachedValue,
        mixed $uncompressedValue,
    ): void {
        /**
         * Mock Redis client, which contains optional overrides for the
         * * already-cached value
         * * value to which the cached value uncompresses
         */
        $redis = new class ($cachedValue, $uncompressedValue) extends Redis
        {
            /**
             * @var array<string, CacheInterface>
             */
            private array $caches = [];

            /**
             * Constructor
             *
             * @param string|null $cachedValueOverride       Optional override for cached value
             * @param mixed       $uncompressedValueOverride Optional override for uncompressed value
             */
            public function __construct(
                private ?string $cachedValueOverride,
                private mixed $uncompressedValueOverride,
            ) {
            }

            /**
             * Build a temporary cache, overriding the cached value if it's set.
             *
             * @param string $baseKey
             * @return CacheInterface
             */
            protected function buildCache(string $baseKey): CacheInterface
            {
                $this->caches[$baseKey] ??= new class (
                    new ArrayAdapter(),
                    $this->cachedValueOverride,
                ) extends Psr16Cache {
                    /**
                     * If true, this will raise an error if `get` misses.
                     */
                    private bool $mustHit = false;

                    /**
                     * Constructor
                     *
                     * @param CacheItemPoolInterface $pool                Internal PSR-6 cache
                     * @param string|null            $cachedValueOverride Optional ovverride for cached value
                     */
                    public function __construct(CacheItemPoolInterface $pool, private ?string $cachedValueOverride)
                    {
                        parent::__construct($pool);
                    }

                    /**
                     * @inheritdoc
                     */
                    public function get($key, $default = null): mixed
                    {
                        if ($this->mustHit && !isset($this->cachedValueOverride) && !$this->has($key)) {
                            throw new \OutOfBoundsException(sprintf('Key "%s" does not exist', $key));
                        }
                        // Need to call get before returning cachedValue, due to internals of Psr16Cache
                        $result = parent::get($key, $default);
                        return $this->cachedValueOverride ?? $result;
                    }

                    /**
                     * Mark this cache as needing to hit on subsequent fetches:
                     * if it does not, it will raise an error.
                     *
                     * @param bool $mustHit
                     * @return $this
                     */
                    public function setMustHit(bool $mustHit): self
                    {
                        $this->mustHit = $mustHit;
                        return $this;
                    }
                };
                return $this->caches[$baseKey];
            }

            /**
             * Return the overridden uncompressed value if set, or the
             * calculated uncompressed value if not.
             *
             * @param string $value
             * @return string|false
             */
            protected function uncompressValue(string $value): string|false
            {
                return $this->uncompressedValueOverride ?? parent::uncompressValue($value);
            }

            /**
             * Mark the internal cache as needing to hit on subsequent fetches:
             * if it does not, it will raise an error.
             *
             * @param bool $mustHit
             * @return $this
             */
            public function setMustHit(bool $mustHit): self
            {
                foreach ($this->caches as $cache) {
                    $cache->setMustHit($mustHit);
                }
                return $this;
            }
        };

        // Test initial call (with cache miss)
        $key = 'key';
        $this->assertSame(
            $expected,
            $redis->cacheFunctionCall($key, fn () => $expected, []),
        );

        $redis->setMustHit(true);

        // Test again, but verify cache hit somehow
        $this->assertSame(
            $expected,
            $redis->cacheFunctionCall($key, fn () => $expected, []),
        );
    }

    /**
     * Data provider for `testCacheFunctionCallCompression`.
     *
     * @return iterable
     */
    public static function getTestCacheFunctionCallData(): iterable
    {
        yield 'not cached' => [
            'q',
            null,
            null,
        ];
        yield 'cached, malformed' => [
            'q',
            'q',
            null,
        ];
        yield 'cached, serialized but uncompressed' => [
            'q',
            serialize('q'),
            null,
        ];
        yield 'cached, serialized and compressed' => [
            'q',
            'gz:' . gzcompress(serialize('q')),
            null,
        ];
        yield 'cached, uncompressed and malformed' => [
            'q',
            'zzzzz',
            null,
        ];
        yield 'cached, compressed and malformed' => [
            'q',
            'gz:' . gzcompress('zzzzz'),
            null,
        ];
        yield 'not cached, uncompression worked as expected' => [
            'q',
            null,
            serialize('q'),
        ];
        yield 'not cached, uncompression failed' => [
            'q',
            null,
            false,
        ];
    }
}
