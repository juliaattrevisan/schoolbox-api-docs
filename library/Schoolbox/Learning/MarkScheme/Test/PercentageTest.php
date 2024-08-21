<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\Percentage;
use RangeException;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\MarkScheme\Percentage::class)]
class PercentageTest extends TestCase
{
    /**
     * @param float  $normalised
     * @param string $expected
     * @throws RangeException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNormalisedToHumanReadable')]
    public function testGetHumanReadableValid($normalised, $expected)
    {
        $markScheme = new Percentage();
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param string $humanReadable
     * @param float  $expected
     * @throws RangeException
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHumanReadableToNormalised')]
    public function testGetNormalisedValid($humanReadable, $expected)
    {
        $markScheme = new Percentage();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    public function testGetNormalisedTooLow()
    {
        $this->expectException(RangeException::class);
        $humanReadable = '-0.0001 %';
        $markScheme = new Percentage();
        $markScheme->getNormalisedMark($humanReadable);
    }


    public function testGetNormalisedTooHigh()
    {
        $this->expectException(RangeException::class);
        $humanReadable = '100.0000001 %';
        $markScheme = new Percentage();
        $markScheme->getNormalisedMark($humanReadable);
    }


    /**
     * @param string|null $formInput
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getConvertFormInputToHumanReadableMarkData')]
    public function testConvertFormInputToHumanReadableMark(
        ?string $formInput,
        ?string $expected
    ): void {
        $markScheme = new Percentage();
        $this->assertSame($expected, $markScheme->convertFormInputToHumanReadableMark($formInput));
    }


    /**
     *
     * @param string|null $formInput
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getConvertFormInputToHumanReadableMarkInvalidData')]
    public function testConvertFormInputToHumanReadableMarkInvalid(?string $formInput): void
    {
        $this->expectException(InvalidArgumentException::class);
        $markScheme = new Percentage();
        $markScheme->convertFormInputToHumanReadableMark($formInput);
    }


    /**
     * @param string|null $humanReadableMark
     * @param string|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getRetrieveHumanReadableMarkAsFormInputData')]
    public function testConvertHumanReadableMarkToFormInput(
        ?string $humanReadableMark,
        ?string $expected
    ): void {
        $markScheme = new Percentage();
        $this->assertSame($expected, $markScheme->convertHumanReadableMarkToFormInput($humanReadableMark));
    }


    // ==================== Data Providers ====================

    /**
     * @return array
     */
    public static function providerHumanReadableToNormalised(): array
    {
        return [
            ['  0 %',      0.0],
            ['  0.0 %',    0.0],
            ['  0.01 %',   0.01],
            ['  0.0001 %', 0.0001],
            ['  0.5 %',    0.5],
            ['  1 %',      1.0],
            ['  1.0 %',    1.0],
            [' 50 %',     50.0],
            [' 99.99 %',  99.99],
            ['100 %',    100.0],
            ['100.0 %',  100.0],
            ['  0 ',       0.0],
            ['  0.0',      0.0],
            ['  0.01 ',    0.01],
            ['  0.0001 ',  0.0001],
            ['  0.5 ',     0.5],
            ['  1 ',       1.0],
            ['  1.0 ',     1.0],
            [' 50 ',      50.0],
            [' 99.99 ',   99.99],
            ['100 ',     100.0],
            ['100.0 ',   100.0],
        ];
    }


    /**
     * @return array
     */
    public static function providerNormalisedToHumanReadable(): array
    {
        return [
            [-0.0001,     '0 %'],
            [0,           '0 %'],
            [0.0,         '0 %'],
            [0.000000000, '0 %'],
            [0.000000001, '0 %'],
            [0.5,         '0.5 %'],
            [0.9,         '0.9 %'],
            [1,           '1 %'],
            [1.0,         '1 %'],
            [1.000000001, '1 %'],
            [10,          '10 %'],
            [10.00000000, '10 %'],
            [99,          '99 %'],
            [99.49999999, '99.5 %'],
            [99.5,        '99.5 %'],
            [100,         '100 %'],
            [100.0,       '100 %'],
            [100.0000001, '100 %'],
        ];
    }


    /**
     * Data provider for `testConvertFormInputToHumanReadableMark`
     *
     * @return iterable
     */
    public static function getConvertFormInputToHumanReadableMarkData(): iterable
    {
        yield 'null form input' => [null, null];
        yield 'numeric form input' => ['50', '50 %'];
        yield 'percent form input, space' => ['50 %', '50 %'];
        yield 'percent form input, no space' => ['50%', '50 %'];
    }


    /**
     * Data provider for `testConvertFormInputToHumanReadableMarkInvalid`
     *
     * @return iterable
     */
    public static function getConvertFormInputToHumanReadableMarkInvalidData(): iterable
    {
        yield 'invalid valid form input 1' => ['Invalid'];
        yield 'invalid valid form input 2' => [''];
    }


    /**
     * Data provider for `testRetrieveHumanReadableValueAsFormInput`
     *
     * @return iterable
     */
    public static function getRetrieveHumanReadableMarkAsFormInputData(): iterable
    {
        yield 'null form input' => [null, null];
        yield 'numeric form input' => ['50', '50'];
        yield 'percent form input, space' => ['50 %', '50'];
        yield 'percent form input, no space' => ['50%', '50'];
    }
}
