<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://www.alaress.com.au/
 */


namespace Alaress\Mail\Test;

use Alaress\Mail\Codec;
use PHPUnit\Framework\TestCase;

class CodecTest extends TestCase
{
    /**
     * @dataProvider provider_testEncode
     * @param $expected
     * @param $result
     */
    public function testEncode($expected, $result)
    {

        $this->assertEquals($expected, Codec::encode($result));
    }

    /**
     * @dataProvider provider_testDecode
     * @param $expected
     * @param $result
     */
    public function testDecode($expected, $result)
    {
        $this->assertEquals($expected, Codec::decode($result));
    }

    /**
     * Convert commas to 0x1B
     * @return array
     */
    public function provider_testEncode()
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
        ];
    }

    /**
     * Convert 0x1B to commas
     * @return array
     */
    public function provider_testDecode() {

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
        ];
    }


    /**
     * @dataProvider  provider_testDecode
     * @param $expected
     * @param $result
     */
    public function testLosslessDecoded($expected, $result)
    {
        $this->assertEquals($expected, Codec::decode(Codec::encode(Codec::decode(Codec::encode($result)))));
    }

    /**
     * @dataProvider  provider_testEncode
     * @param $expected
     * @param $result
     */
    public function testLosslessEncoded($expected, $result)
    {
        $this->assertEquals($expected, Codec::encode(Codec::decode(Codec::encode(Codec::decode($result)))));
    }
}
