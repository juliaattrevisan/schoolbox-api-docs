<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec\Test;

use Alaress\Codec\Json\Base64;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Test class for the JSON-base64 codec.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Codec\Json\Base64::class)]
class JsonBase64Test extends TestCase
{
    /**
     * Get data for `testEncode` and `testDecode`.
     *
     * @return iterable
     */
    public static function getTestEncodeData(): iterable
    {
        // Integers
        yield '0'      => [0, 'MA=='];
        yield '1'      => [1, 'MQ=='];
        yield '-1'     => [-1, 'LTE='];
        yield '99999'  => [99999, 'OTk5OTk='];
        yield '-99999' => [-99999, 'LTk5OTk5'];

        // Strings
        yield '"a"'          => ['a', 'ImEi'];
        yield '"qwertyuiop"' => ['qwertyuiop', 'InF3ZXJ0eXVpb3Ai'];
        yield '"qwertyuioP"' => ['qwertyuioP', 'InF3ZXJ0eXVpb1Ai'];
        yield '"Лорем"'      => ['Лорем', 'Ilx1MDQxYlx1MDQzZVx1MDQ0MFx1MDQzNVx1MDQzYyI='];

        // Floats
        yield '0.5'  => [0.5, 'MC41'];
        yield '-0.5' => [-0.5, 'LTAuNQ=='];

        // Arrays
        yield 'flat list' => [[1,2,3], 'WzEsMiwzXQ=='];
        yield 'flat dict' => [['a' => 1, 'b' => 2, 'c' => 3], 'eyJhIjoxLCJiIjoyLCJjIjozfQ=='];
        yield 'nested list' => [[[1,2,3],'a',['b' => 'c']], 'W1sxLDIsM10sImEiLHsiYiI6ImMifV0='];
        yield 'nested dict' => [['x' => [1,2,3], 'y' => 'a', 'z' => ['b' => 'c']], 'eyJ4IjpbMSwyLDNdLCJ5IjoiYSIsInoiOnsiYiI6ImMifX0='];
    }


    /**
     * Get data for `testInvalidEncodedValue`.
     *
     * @return iterable
     */
    public static function getTestInvalidEncodedValueData(): iterable
    {
        yield 'null'     => [null];
        yield 'int'      => [1];
        yield 'object'   => [new \stdClass()];
        yield 'array'    => [[1,2,3]];
        yield 'bool'     => [false];
        yield 'float'    => [1.2];
        yield 'resource' => [STDIN];
    }


    /**
     * @param mixed  $rawValue
     * @param string $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testEncode($rawValue, string $encodedValue): void
    {
        $codec = new Base64();
        self::assertSame($encodedValue, $codec->encode($rawValue));
    }


    /**
     * @param mixed  $rawValue
     * @param string $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testDecode($rawValue, string $encodedValue): void
    {
        $codec = new Base64();
        self::assertSame($rawValue, $codec->decode($encodedValue));
    }


    /**
     * Test that encoding then decoding a value results in the same value.
     *
     * @param mixed  $rawValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testLosslessEncode($rawValue): void
    {
        $codec = new Base64();
        self::assertSame($rawValue, $codec->decode($codec->encode($rawValue)));
    }


    /**
     * Test that decoding then encoding a value results in the same encoded
     * value.
     *
     * @param int    $rawValue
     * @param string $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testLosslessDecode($rawValue, string $encodedValue): void
    {
        $codec = new Base64();
        self::assertSame($encodedValue, $codec->encode($codec->decode($encodedValue)));
    }


    /**
     * Test that attempting to decode a non-string raises an error.
     *
     *
     * @param mixed $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvalidEncodedValueData')]
    public function testInvalidEncodedValue($encodedValue): void
    {
        $this->expectException(TypeError::class);
        $codec = new Base64();
        $codec->decode($encodedValue);
    }
}
