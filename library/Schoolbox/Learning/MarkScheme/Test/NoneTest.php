<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\None;
use \InvalidArgumentException;
use \RangeException;

class NoneTest extends TestCase
{
    public function test_getHumanReadable_Null()
    {
        $normalised = null;
        $markScheme = new None();
        $markScheme->getHumanReadableMark($normalised);
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



    public function test_GetNormalised_Null()
    {
        $humanReadable = null;
        $expected = null;
        $markScheme = new None();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

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

    public function provider_normalised()
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


    public function provider_humanReadable()
    {
        return [
            [null],
            [''],
            ['a big dinosaur'],
            [20.5],
        ];
    }
}
