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

        $msgId = $generator->generate('22252BE2C7309F8C35D8A521897EFE16');

        $this->assertSame(96, strlen($msgId));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{96}$/', $msgId);
        $this->assertSame('0123456789ABCDEF0123456789ABCDEF', substr($msgId, 0, 32));
        $this->assertSame(
            '4F3A1DB60B9B9DA9B73C035BD2DCB2FEE63C6755F57FB222754A9516E8A69F3E',
            substr($msgId, 32)
        );
    }
}
