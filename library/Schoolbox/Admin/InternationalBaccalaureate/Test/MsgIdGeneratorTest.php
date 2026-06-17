<?php

/**
 * IBIS msgID generator test
 *
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Admin\InternationalBaccalaureate\MsgIdGenerator;

class MsgIdGeneratorTest extends TestCase
{
    public function testRejectsInvalidHexKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new MsgIdGenerator())->generate('not-a-hex-key');
    }

    public function testProducesMsgIdFromPlainTextAndSha256Hash(): void
    {
        $generator = new MsgIdGenerator(
            fn() => '0123456789ABCDEF0123456789ABCDEF'
        );

        $msgId = $generator->generate('31225BE2C7309F8C35D8A521897EFE16');

        $this->assertSame(96, strlen($msgId));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{96}$/', $msgId);
        $this->assertSame('0123456789ABCDEF0123456789ABCDEF', substr($msgId, 0, 32));
        $this->assertSame(
            'A7D61859EF31AEA07CDEABCD38B2D7E88F2085AB390CB2B8A6CF5FDD9CCD4F4B',
            substr($msgId, 32)
        );
    }
}
