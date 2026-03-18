<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Codec\Test;

use DomainException;
use PHPUnit\Framework\TestCase;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\Codec\DSN;

class DSNTest extends TestCase
{
    public function testEncode(): void
    {
        $dsn = new DSN();
        $result = $dsn->encode(['token' => 'abc123']);
        $this->assertSame('token=abc123', $result);
    }

    public function testDecode(): void
    {
        $dsn = new DSN();
        $result = $dsn->decode('token=abc123');
        $this->assertSame(['token' => 'abc123'], $result);
    }

    public function testDecodeThrowsOnBadFormat(): void
    {
        $this->expectException(DomainException::class);
        (new DSN())->decode('garbage');
    }

    public function testRoundTrip(): void
    {
        $dsn = new DSN();
        $parts = ['token' => 'my-secret-token-value'];
        $encoded = $dsn->encode($parts);
        $decoded = $dsn->decode($encoded);
        $this->assertSame($parts, $decoded);
    }
}
