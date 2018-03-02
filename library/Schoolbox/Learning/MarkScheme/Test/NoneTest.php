<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
}
