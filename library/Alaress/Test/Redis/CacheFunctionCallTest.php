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
        // Mock Redis client
        $redis = new class (
            $cachedValue,
            $uncompressedValue,
        ) extends Redis {
            private array $caches = [];
            public function __construct(private ?string $cachedValue, private mixed $uncompressedValue)
            {
            }

            protected function buildCache(string $baseKey): CacheInterface
            {
                $this->caches[$baseKey] ??= new class (
                    new ArrayAdapter(),
                    $this->cachedValue,
                ) extends Psr16Cache {
                    private bool $mustHit = false;
                    public function __construct(CacheItemPoolInterface $pool, private ?string $cachedValue)
                    {
                        parent::__construct($pool);
                    }

                    public function get($key, $default = null): mixed
                    {
                        if ($this->mustHit && !isset($this->cachedValue) && !$this->has($key)) {
                            throw new \OutOfBoundsException(sprintf('Key "%s" does not exist', $key));
                        }
                        // Need to call get before returning cachedValue, due to internals of Psr16Cache
                        $result = parent::get($key, $default);
                        return $this->cachedValue ?? $result;
                    }

                    public function setMustHit(bool $mustHit): self
                    {
                        $this->mustHit = $mustHit;
                        return $this;
                    }
                };
                return $this->caches[$baseKey];
            }

            protected function uncompressValue(string $value): string|false
            {
                return $this->uncompressedValue ?? parent::uncompressValue($value);
            }
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
