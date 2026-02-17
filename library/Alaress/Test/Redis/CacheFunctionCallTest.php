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
        mixed $uncompressedValue,
    ): void {
        // Mock Redis client
        $redis = new class (
            $cachedValue,
            $uncompressedValue,
        ) extends Redis {
            private array $cachedValues = [];
            private bool $mustHit = false;
            public function __construct(private ?string $cachedValue, private mixed $uncompressedValue)
            {
            }
            protected function uncompressValue(string $value): string|false
            {
                return $this->uncompressedValue ?? parent::uncompressValue($value);
            }
            protected function fetchCachedValue(string $key): ?string
            {
                if (!array_key_exists($key, $this->cachedValues)) {
                    if ($this->mustHit) {
                        throw new \OutOfBoundsException(sprintf('Key "%s" does not exist', $key));
                    }
                    $this->storeCachedValue($key, $this->cachedValue, null);
                    return $this->cachedValue;
                }
                return $this->cachedValues[$key];
            }
            protected function storeCachedValue(string $key, string $value, ?int $timeout): void
            {
                $this->cachedValues[$key] = $value;
            }
            public function setMustHit(bool $mustHit): self
            {
                $this->mustHit = $mustHit;
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
    }
}
