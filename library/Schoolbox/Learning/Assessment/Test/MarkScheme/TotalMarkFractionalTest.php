<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use \InvalidArgumentException;
use \RangeException;

class TotalMarkFractionalTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException RangeException
     */
    public function test_TotalMarkFractional_NegativeTotal()
    {
        $input = -1.0;
        new TotalMarkFractional($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_SetTotalNegative()
    {
        $input = -50.0;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->setTotalMark($input);
    }

    /**
     * @expectedException RangeException
     */
    public function test_SetTotalExceedOriginal()
    {
        $input = 60.0;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->setTotalMark($input);
    }

    /**
     * @dataProvider provider_setValidTotal
     * @param float $totalMark
     * @param float $originalTotalMark
     */
    public function test_SetTotalValid($totalMark, $originalTotalMark)
    {
        $markScheme = new TotalMarkFractional($originalTotalMark);
        $markScheme->setTotalMark($totalMark);

    }

    /**
     * @expectedException RangeException
     */
    public function test_getHumanReadable_tooLow()
    {
        $normalised = -1.0;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException RangeException
     */
    public function test_getHumanReadable_tooHigh()
    {
        $normalised = 100.0001;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getHumanReadableMark($normalised);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Null()
    {
        $humanReadable = null;
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Empty()
    {
        $humanReadable = '';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function test_getNormalised_Invalid()
    {
        $humanReadable = 'ten';
        $totalMark = 50.0;
        $markScheme = new TotalMarkFractional($totalMark);
        $markScheme->getNormalisedMark($humanReadable);
    }

    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param float $totalMark
     * @param float $expected
     */
    public function test_getNormalised($humanReadable, $totalMark, $expected)
    {
        $markScheme = new TotalMarkFractional($totalMark);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @dataProvider provider_humanReadableToRecurringNormalised
     * @param string $humanReadable
     * @param float $totalMark
     * @param float $expected
     */
    public function test_getRecurringNormalised($humanReadable, $totalMark, $expected)
    {
        $delta = 0.00001;
        $markScheme = new TotalMarkFractional($totalMark);
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertLessThan($delta, abs($expected - $actual), $message);
    }

    // ==================== Data Providers ====================

    public function provider_humanReadableToRecurringNormalised()
    {
        $totalMark = 3.0;
        return [
            ['0',   $totalMark, 0.0],
            ['1',   $totalMark, 33.33333333],
            ['2',   $totalMark, 66.66666666],
            ['3',   $totalMark, 100.0],
            ['0.1', $totalMark, 3.33333333],
        ];
    }

    public function provider_setValidTotal()
    {
        $originalTotalMark = 50;
        return [
            [0,         $originalTotalMark],
            [0.0,       $originalTotalMark],
            [10.0,      $originalTotalMark],
            [49.999999, $originalTotalMark],
            [50,        $originalTotalMark],
            [50.0,      $originalTotalMark],
            [50.000000, $originalTotalMark],
        ];
    }

    public function provider_humanReadableToNormalised()
    {
        $totalMark = 100;
        return [
            ['0',          $totalMark, 0.0],
            ['0.0',        $totalMark, 0.0],
            [' 0',         $totalMark, 0.0],
            [' 0 ',        $totalMark, 0.0],
            ['1.5',        $totalMark, 1.5],
            ['50',         $totalMark, 50.0],
            ['66.6666666', $totalMark, 66.6666666],
            ['99.99',      $totalMark, 99.99],
            ['100',        $totalMark, 100.0],
        ];
    }
}