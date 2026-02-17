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

#[CoversMethod(Redis::class, 'cacheFunctionCall')]
class CacheFunctionCallTest extends TestCase
{
    #[DataProvider('getTestCacheFunctionCallData')]
    public function testCacheFunctionCallCompression(
        mixed $expected,
        ?string $cachedValue,
        mixed $compressedValue,
    ): void {
        // Mock Redis client
        $redis = new class ($cachedValue, $compressedValue) extends Redis {
            private array $cachedValues = [];
            public function __construct(private ?string $cachedValue, private mixed $uncompressedValue)
            {
            }
            protected function uncompressValue(string $value): string|false
            {
                return $this->uncompressedValue ?? parent::uncompressValue($value);
            }
            protected function fetchCachedValue(string $key): ?string
            {
                return array_key_exists($key, $this->cachedValues)
                    ? $this->cachedValues[$key]
                    : $this->cachedValue;
            }
            protected function storeCachedValue(string $key, string $value, ?int $timeout): void
            {
                $this->cachedValues[$key] = $value;
            }
        };

        // Test initial call (with cache miss)
        $this->assertSame(
            $expected,
            $redis->cacheFunctionCall(
                'get',
                fn () => $expected,
                [],
            ),
        );

        // Test again, but verify cache hit
        $this->assertSame(
            $expected,
            $redis->cacheFunctionCall(
                'get',
                fn () => $expected,
                [],
            ),
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
        yield 'cached, serialized but uncompressed' => [
            'q',
            null,
            serialize('q'),
        ];
        yield 'cached, serialized and compressed' => [
            'q',
            null,
            'gz:' . gzcompress(serialize('q')),
        ];
        yield 'cached, uncompressed and malformed' => [
            'q',
            null,
            'zzzzz',
        ];
        yield 'cached, compressed and malformed' => [
            'q',
            null,
            'gz:' . gzcompress('zzzzz'),
        ];
    }
}
