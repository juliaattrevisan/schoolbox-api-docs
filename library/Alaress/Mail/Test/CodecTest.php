<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Mail\Test;

use Alaress\Mail\Codec;
use PHPUnit\Framework\TestCase;

class CodecTest extends TestCase
{
    /**
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTestEncode')]
    public function testEncode($input, $expected)
    {
        $this->assertEquals($expected, Codec::encode($input));
    }

    /**
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTestDecode')]
    public function testDecode($input, $expected)
    {
        $this->assertEquals($expected, Codec::decode($input));
    }

    /**
     * @param string $input
     * @param string $expected
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataIsNull')]
    public function testInvalidDecodeData($input, $expected)
    {
        $this->expectException(\TypeError::class);
        $this->assertEquals($expected, Codec::decode($input));
    }

    /**
     * @param string $input
     * @param string $expected
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDataIsNull')]
    public function testInvalidEncodeData($input, $expected)
    {
        $this->expectException(\TypeError::class);
        $this->assertEquals($expected, Codec::encode($input));
    }

    /**
     * Convert commas to 0x1B
     * @return array
     */
    public static function providerTestEncode()
    {
        return [
            [
                '"English 3 4 (EN344, EN123)" <alaress@schoolbox.com.au>',
                '"English 3 4 (EN344 EN123)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 (EN344,ENGU4A)" <alaress@schoolbox.com.au>, "English 5 6 (EN56,ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 (EN344ENGU4A)" <alaress@schoolbox.com.au> "English 5 6 (EN56ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 "But this is just, a trick" (EN344,ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 "But this is just a trick" (EN344ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 "But this is just, a trick" (and another, trick yo!) (EN344,ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 "But this is just a trick" (and another trick yo!) (EN344ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3, 4 "But this is just, a trick" (and another, trick yo!) (EN344,ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 "But this is just a trick" (and another trick yo!) (EN344ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3, 4, "But this is just, a trick" (and another, trick, yo!) (EN344,ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 "But this is just a trick" (and another trick yo!) (EN344ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                'test@test.com; test@test.com',
                'test@test.com; test@test.com'
            ],
            [
                'test@test.com, test@test.com',
                'test@test.com test@test.com'
            ],
            [
                '',
                ''
            ],
        ];
    }

    /**
     * Convert 0x1B to commas
     * @return array
     */
    public static function providerTestDecode()
    {
        return [
            [
                '"English 3 4 (EN344 EN123)" <alaress@schoolbox.com.au>',
                '"English 3 4 (EN344, EN123)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 (EN344ENGU4A)" <alaress@schoolbox.com.au> "English 5 6 (EN56ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 (EN344,ENGU4A)" <alaress@schoolbox.com.au>, "English 5 6 (EN56,ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 "But this is just a trick" (EN344ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 "But this is just, a trick" (EN344,ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 "But this is just a trick" (and another trick yo!) (EN344ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3 4 "But this is just, a trick" (and another, trick yo!) (EN344,ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 "But this is just a trick" (and another trick yo!) (EN344ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3, 4 "But this is just, a trick" (and another, trick yo!) (EN344,ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                '"English 3 4 "But this is just a trick" (and another trick yo!) (EN344ENGU4A)" <alaress@schoolbox.com.au>',
                '"English 3, 4, "But this is just, a trick" (and another, trick, yo!) (EN344,ENGU4A)" <alaress@schoolbox.com.au>'
            ],
            [
                'test@test.com; test@test.com',
                'test@test.com; test@test.com'
            ],
            [
                'test@test.com test@test.com',
                'test@test.com, test@test.com'
            ],
            [
                '',
                ''
            ],
        ];
    }

    /**
     * What is the data is null? It should give us a TypeError
     * @return array
     */
    public static function providerDataIsNull()
    {
        return [
            [
                null,
                false,
            ]
        ];
    }

    /**
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTestDecode')]
    public function testLosslessDecoded($input, $expected)
    {
        $this->assertEquals($expected, Codec::decode(Codec::encode(Codec::decode(Codec::encode($input)))));
    }

    /**
     * @param string $input
     * @param string $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerTestEncode')]
    public function testLosslessEncoded($input, $expected)
    {
        $this->assertEquals($expected, Codec::encode(Codec::decode(Codec::encode(Codec::decode($input)))));
    }
}
