<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\None;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Learning\MarkScheme\None::class)]
class NoneTest extends TestCase
{
    /**
     * Humanreadable is null
     */
    public function testGetHumanReadableNull()
    {
        $normalised = null;
        $expected = null;
        $markScheme = new None();

        $actual = $markScheme->getHumanReadableMark($normalised);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @param float $normalised
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerNormalised')]
    public function testGetHumanReadableValid($normalised)
    {
        $expected = null;
        $markScheme = new None();

        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * Normalised mark is null
     */
    public function testGetNormalisedNull()
    {
        $humanReadable = null;
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * Normalised mark is empty
     */
    public function testGetNormalisedEmpty()
    {
        $humanReadable = '';
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @param string $humanReadable
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerHumanReadable')]
    public function testGetNormalisedValid($humanReadable)
    {
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $this->assertEquals($expected, $actual);
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
        $markScheme = new None();
        $this->assertSame($expected, $markScheme->convertFormInputToHumanReadableMark($formInput));
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
        $markScheme = new None();
        $this->assertSame($expected, $markScheme->convertHumanReadableMarkToFormInput($humanReadableMark));
    }


    // ==================== Data Providers ====================

    /**
     * @return array
     */
    public static function providerNormalised(): array
    {
        return [
            [0],
            [0.0],
            [50],
            [99.99],
            [100],
            [100.000],
        ];
    }


    /**
     * @return array
     */
    public static function providerHumanReadable(): array
    {
        return [
            [null],
            [''],
            ['a big dinosaur'],
            [20.5],
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
        yield 'string form input' => ['red', null];
        yield 'numeric form input' => ['123', null];
    }


    /**
     * Data provider for `testRetrieveHumanReadableValueAsFormInput`
     *
     * @return iterable
     */
    public static function getRetrieveHumanReadableMarkAsFormInputData(): iterable
    {
        yield 'null form input' => [null, null];
        yield 'string form input' => ['red', null];
        yield 'numeric form input' => ['123', null];
    }
}
