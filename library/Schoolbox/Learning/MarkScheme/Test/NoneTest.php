<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\None;

class NoneTest extends TestCase
{
    /**
     * Humanreadable is null
     */
    public function test_getHumanReadable_Null()
    {
        $normalised = null;
        $expected = null;
        $markScheme = new None();

        $actual = $markScheme->getHumanReadableMark($normalised);

        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }


    /**
     * @dataProvider provider_normalised
     * @param float $normalised
     */
    public function test_getHumanReadable_Valid($normalised)
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
    public function test_GetNormalised_Null()
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
    public function test_GetNormalised_Empty()
    {
        $humanReadable = '';
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_humanReadable
     * @param string $humanReadable
     */
    public function test_GetNormalised_Valid($humanReadable)
    {
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider getConvertFormInputToHumanReadableMarkData
     * @covers \Schoolbox\Learning\MarkScheme\None::convertFormInputToHumanReadableMark
     *
     * @param string|null $formInput
     * @param string|null $expected
     */
    public function testConvertFormInputToHumanReadableMark(
        ?string $formInput,
        ?string $expected
    ): void {
        $markScheme = new None();
        $this->assertSame($expected, $markScheme->convertFormInputToHumanReadableMark($formInput));
    }


    /**
     * @dataProvider getRetrieveHumanReadableMarkAsFormInputData
     * @covers \Schoolbox\Learning\MarkScheme\None::convertHumanReadableMarkToFormInput
     *
     * @param string|null $humanReadableMark
     * @param string|null $expected
     */
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
    public function provider_normalised(): array
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
    public function provider_humanReadable(): array
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
    public function getConvertFormInputToHumanReadableMarkData(): iterable
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
    public function getRetrieveHumanReadableMarkAsFormInputData(): iterable
    {
        yield 'null form input' => [null, null];
        yield 'string form input' => ['red', null];
        yield 'numeric form input' => ['123', null];
    }
}
