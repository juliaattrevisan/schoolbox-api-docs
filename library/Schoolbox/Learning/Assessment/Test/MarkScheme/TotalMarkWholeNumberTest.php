<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use \InvalidArgumentException;
use \RangeException;

class TotalMarkWholeNumberTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function test_Fractional_Denominator()
    {
        $input = 10.5;
        new TotalMarkWholeNumber($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_Negative_Denominator()
    {
        $input = -10;
        new TotalMarkWholeNumber($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_Zero_Denominator()
    {
        $input = 0;
        new TotalMarkWholeNumber($input);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function test_setDenominator()
    {
        $input = 50;
        $markScheme = new TotalMarkWholeNumber(50);
        $markScheme->setTotalMark($input);
    }



    /**
     * @dataProvider provider_normalisedToHumanReadable
     * @param $normalised
     * @param $totalMark
     * @param $expected
     */
    public function test_getHumanReadable($normalised, $totalMark, $expected)
    {
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @expectedException RangeException
     */
    public function test_GetHumanReadable_NegativeNumerator()
    {
        $normalised = -10;
        $totalMark = 100;
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException RangeException
     */
    public function test_NumeratorTooHigh()
    {
        $normalised = 100.25;
        $totalMark = 100;
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $markScheme->getHumanReadableMark($normalised);
    }




    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param int $totalMark
     * @param float $expected
     */
    public function test_getNormalised($humanReadable, $totalMark, $expected)
    {
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }



    /**
     * @dataProvider provider_humanReadableToRecurringNormalised
     * @param string $humanReadable
     * @param int $totalMark
     * @param float $expected
     */
    public function test_getNormalised_Recurring($humanReadable, $totalMark, $expected)
    {
        $delta = 0.00001;
        $markScheme = new TotalMarkWholeNumber($totalMark);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertLessThan($delta, abs($expected - $actual), $message);

    }

    // ==================== Data Providers ====================

    public function provider_humanReadableToRecurringNormalised()
    {
        $totalMark = 3;
        return [
            ['0', $totalMark, 0.0],
            ['1', $totalMark, 33.333333333],
            ['3', $totalMark, 100.0],
        ];
    }

    public function provider_humanReadableToNormalised()
    {
        $totalMark = 100;
        return [
            ['0',   $totalMark, 0.0],
            [' 0',  $totalMark, 0.0],
            ['0.0', $totalMark, 0.0],
            ['0 ',  $totalMark, 0.0],
            ['1',   $totalMark, 1.0],
            ['45',  $totalMark, 45.0],
            ['100', $totalMark, 100.0],
        ];
    }

    public function provider_normalisedToHumanReadable()
    {
        $totalMark = 100;
        return [
            [0,     $totalMark,     '0 / 100'],
            [0.1,   $totalMark,     '0 / 100'],
            [0.2,   $totalMark,  '0.25 / 100'],
            [0.3,   $totalMark,  '0.25 / 100'],
            [01,    $totalMark,     '1 / 100'],
            [1.0,   $totalMark,     '1 / 100'],
            [99.4,  $totalMark,  '99.5 / 100'],
            [99.7,  $totalMark, '99.75 / 100'],
            [99.9,  $totalMark,   '100 / 100'],
            [100,   $totalMark,   '100 / 100'],
            [100.0, $totalMark,   '100 / 100'],
        ];
    }

}