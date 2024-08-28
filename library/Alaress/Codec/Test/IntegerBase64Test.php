<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Codec\Test;

use Alaress\Codec\Integer\Base64;
use PHPUnit\Framework\TestCase;

/**
 * Test class for the integer-base64 codec.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Alaress\Codec\Integer\Base64::class)]
class IntegerBase64Test extends TestCase
{
    /**
     * Get data for `testEncode` and `testDecode`.
     *
     * @return iterable
     */
    public static function getTestEncodeData(): iterable
    {
        yield '0'      => [0,      'MA=='];
        yield '1'      => [1,      'MQ=='];
        yield '-1'     => [-1,     'LTE='];
        yield '99999'  => [99999,  'OTk5OTk='];
        yield '-99999' => [-99999, 'LTk5OTk5'];
    }


    /**
     * Get data for `testInvalidRawValue`.
     *
     * @return iterable
     */
    public static function getTestInvalidRawValueData(): iterable
    {
        yield 'null'        => [null];
        yield 'emptyString' => [''];
        yield 'string'      => ['1'];
        yield 'object'      => [new \stdClass()];
        yield 'array'       => [[1,2,3]];
        yield 'bool'        => [false];
        yield 'float'       => [1.2];
        yield 'resource'    => [STDIN];
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
     * Get data for `testInvalidEncodedValue`.
     *
     * @return iterable
     */
    public static function getTestEncodedValueDecodesToNonIntegerErrorData(): iterable
    {
        yield 'emptyString' => [''];
        yield 'float 4.5'   => ['NC41'];
        yield 'string 4e4'  => ['NGU0'];
        yield 'string xxx'  => ['eHh4'];
    }


    /**
     * @param int|null    $rawValue
     * @param string|null $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testEncode(?int $rawValue, ?string $encodedValue): void
    {
        $codec = new Base64();
        $this->assertSame($encodedValue, $codec->encode($rawValue));
    }


    /**
     * @param int|null    $rawValue
     * @param string|null $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testDecode(?int $rawValue, ?string $encodedValue): void
    {
        $codec = new Base64();
        $this->assertSame($rawValue, $codec->decode($encodedValue));
    }


    /**
     * Test that encoding then decoding a value results in the same value.
     *
     * @param int|null $rawValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testLosslessEncode(?int $rawValue): void
    {
        $codec = new Base64();
        $this->assertSame($rawValue, $codec->decode($codec->encode($rawValue)));
    }


    /**
     * Test that decoding then encoding a value results in the same encoded
     * value.
     *
     * @param int|null    $rawValue
     * @param string|null $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodeData')]
    public function testLosslessDecode(?int $rawValue, ?string $encodedValue): void
    {
        $codec = new Base64();
        $this->assertSame($encodedValue, $codec->encode($codec->decode($encodedValue)));
    }


    /**
     * Test that attempting to encode a non-integer raises an error.
     *
     * @param mixed $rawValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestInvalidRawValueData')]
    public function testInvalidRawValue($rawValue): void
    {
        $this->expectException(\TypeError::class);
        $codec = new Base64();
        $codec->encode($rawValue);
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
        $this->expectException(\TypeError::class);
        $codec = new Base64();
        $codec->decode($encodedValue);
    }


    /**
     * Test that attempting to decode a value that decodes to a non-integer
     * raises an error.
     *
     *
     * @param mixed $encodedValue
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestEncodedValueDecodesToNonIntegerErrorData')]
    public function testEncodedValueDecodesToNonIntegerError($encodedValue): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $codec = new Base64();
        $codec->decode($encodedValue);
    }
}
