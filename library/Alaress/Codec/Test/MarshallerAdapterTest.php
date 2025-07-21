<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Codec\Test;

use Alaress\Codec\ICodec;
use Alaress\Codec\MarshallerAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use Schoolbox\Test\TestCase;
use TypeError;

/**
 * Test case for `\Alaress\Codec\MarshallerAdapter`.
 */
#[CoversClass(MarshallerAdapter::class)]
class MarshallerAdapterTest extends TestCase
{
    /**
     * Test for `\Alaress\Codec\MarshallerAdapter::marshall`.
     *
     * @param ICodec $codec          Codec to adapt.
     * @param array  $valuesToEncode Values to pass to `marshall`.
     * @param array  $expected       Expected output from `marshall`.
     * @return void
     */
    #[DataProvider('getTestMarshallData')]
    public function testMarshall(ICodec $codec, array $valuesToEncode, array $expected): void
    {
        $marshaller = new MarshallerAdapter($codec);
        $actual     = $marshaller->marshall($valuesToEncode, $failed);
        $this->assertSame($expected, $actual);
    }

    /**
     * Test for `\Alaress\Codec\MarshallerAdapter::marshall`, if it fails one
     * or more values.
     *
     * @param ICodec $codec            Codec to adapt.
     * @param array  $valuesToEncode   Values to pass to `marshall`.
     * @param array  $expected         Expected output from `marshall`.
     * @param array  $expectedFailures Expected failure outputs from `marshall`.
     * @return void
     */
    #[DataProvider('getTestMarshallWithFailuresData')]
    public function testMarshallWithFailures(
        ICodec $codec,
        array $valuesToEncode,
        array $expected,
        array $expectedFailures,
    ): void {
        $marshaller = new MarshallerAdapter($codec);
        $actual     = $marshaller->marshall($valuesToEncode, $failed);
        $this->assertSame($expected, $actual);
        $this->assertSame($expectedFailures, $failed);
    }

    /**
     * Test for `\Alaress\Codec\MarshallerAdapter::unmarshall`.
     *
     * @param ICodec $codec         Codec to adapt.
     * @param string $valueToDecode Value to pass to `unmarshall`.
     * @param string $expected      Expected output from `unmarshall`.
     * @return void
     * @throws \Exception
     */
    #[DataProvider('getTestUnmarshallData')]
    public function testUnmarshall(
        ICodec $codec,
        string $valueToDecode,
        string $expected,
    ): void {
        $marshaller = new MarshallerAdapter($codec);
        $actual     = $marshaller->unmarshall($valueToDecode);
        $this->assertSame($expected, $actual);
    }

    /**
     * Test for `\Alaress\Codec\MarshallerAdapter::unmarshall`, if unmarshalling
     * fails.
     *
     * @param ICodec $codec Codec to adapt.
     * @param string $valueToDecode Value to pass to `unmarshall`.
     * @return void
     * @throws \Exception
     */
    #[DataProvider('getTestUnmarshallFailureData')]
    public function testUnmarshallFailure(
        ICodec $codec,
        string $valueToDecode,
    ): void {
        $this->expectException(TypeError::class);
        $marshaller = new MarshallerAdapter($codec);
        $marshaller->unmarshall($valueToDecode);
    }

    /**
     * Data provider for `testMarshall`.
     *
     * @return iterable
     */
    public static function getTestMarshallData(): iterable
    {
        $codec = self::buildTestCodec();
        yield 'simple marshall' => [
            $codec,
            ['12345'],
            ['12345X'],
        ];
        yield 'multiple marshall' => [
            $codec,
            ['12345', '23456', 'YXXX'],
            ['12345X', '23456X', 'YXXXX'],
        ];
    }

    /**
     * Data provider for `testMarshallWithFailures`.
     *
     * @return iterable
     */
    public static function getTestMarshallWithFailuresData(): iterable
    {
        $codec = self::buildTestCodec();
        yield 'all failed marshall' => [
            $codec,
            ['XXX'],
            [],
            [0],
        ];
        yield 'some failed marshall' => [
            $codec,
            ['12345', 'X23456', 'YXXX'],
            [
                0 => '12345X',
                2 => 'YXXXX'
            ],
            [1],
        ];
    }

    /**
     * Data provider for `testUnmarshall`.
     *
     * @return iterable
     */
    public static function getTestUnmarshallData(): iterable
    {
        $codec = self::buildTestCodec();
        yield 'simple unmarshall' => [
            $codec,
            '12345X',
            '12345',
        ];
    }

    /**
     * Data provider for `testUnmarshallFailure`.
     *
     * @return iterable
     */
    public static function getTestUnmarshallFailureData(): iterable
    {
        $codec = self::buildTestCodec();
        yield 'simple unmarshall failure' => [
            $codec,
            '12345',
        ];
    }

    /**
     * Build an extremely simple codec for test purposes.
     *
     * @return ICodec
     */
    private static function buildTestCodec(): ICodec
    {
        return new class implements ICodec {
            public function encode($rawValue)
            {
                if (str_starts_with($rawValue, 'X')) {
                    throw new TypeError('Raw value must not start with X');
                }
                return $rawValue . 'X';
            }

            public function decode($encodedValue)
            {
                if (!str_ends_with($encodedValue, 'X')) {
                    throw new TypeError('Encoded value must end with X');
                }
                return substr($encodedValue, 0, -1);
            }
        };
    }
}
