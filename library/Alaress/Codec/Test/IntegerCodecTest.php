<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec\Test;

use Alaress\Codec\IntegerCodec;
use PHPUnit\Framework\TestCase;

/**
 * Test class for `IntegerCodec`.
 *
 * @covers \Alaress\Codec\IntegerCodec
 */
class IntegerCodecTest extends TestCase
{
    /**
     * Get data for `testEncode` and `testDecode`.
     *
     * @return iterable
     */
    public function getTestEncodeData(): iterable
    {
        yield 'null'   => [null,   null];
        yield '0'      => [0,      'MA=='];
        yield '1'      => [1,      'MQ=='];
        yield '-1'     => [-1,     'LTE='];
        yield '99999'  => [99999,  'OTk5OTk='];
        yield '-99999' => [-99999, 'LTk5OTk5'];
    }


    /**
     * @covers       \Alaress\Codec\IntegerCodec::encode
     * @dataProvider getTestEncodeData
     * @param int|null    $rawValue
     * @param string|null $encodedValue
     */
    public function testEncode(?int $rawValue, ?string $encodedValue): void
    {
        $codec = new IntegerCodec();
        $this->assertSame($encodedValue, $codec->encode($rawValue));
    }


    /**
     * @covers       \Alaress\Codec\IntegerCodec::decode
     * @dataProvider getTestEncodeData
     * @param int|null    $rawValue
     * @param string|null $encodedValue
     */
    public function testDecode(?int $rawValue, ?string $encodedValue): void
    {
        $codec = new IntegerCodec();
        $this->assertSame($rawValue, $codec->decode($encodedValue));
    }


    /**
     * Test that encoding then decoding a value results in the same value.
     *
     * @covers       \Alaress\Codec\IntegerCodec::encode
     * @covers       \Alaress\Codec\IntegerCodec::decode
     * @dataProvider getTestEncodeData
     * @param int|null $rawValue
     */
    public function testLosslessEncode(?int $rawValue): void
    {
        $codec = new IntegerCodec();
        $this->assertSame($rawValue, $codec->decode($codec->encode($rawValue)));
    }


    /**
     * Test that decoding then encoding a value results in the same encoded
     * value.
     *
     * @covers       \Alaress\Codec\IntegerCodec::encode
     * @covers       \Alaress\Codec\IntegerCodec::decode
     * @dataProvider getTestEncodeData
     * @param int|null    $rawValue     Unused:
     * @param string|null $encodedValue
     */
    public function testLosslessDecode(?int $rawValue, ?string $encodedValue): void
    {
        $codec = new IntegerCodec();
        $this->assertSame($encodedValue, $codec->encode($codec->decode($encodedValue)));
    }
}
