<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\TotalMarkWholeNumber;
use \InvalidArgumentException;
use \RangeException;

class TotalMarkWholeNumberTest extends TestCase
{

    /**
     * @dataProvider provider_validStringDenominators
     * @param string $validStringTotalMark
     */
    public function test_valid_string_Denominator($validStringTotalMark)
    {
        new TotalMarkWholeNumber($validStringTotalMark);
    }


    /**
     * @dataProvider provider_invalidStringDenominators
     * @param string $invalidStringTotalMark
     * @expectedException InvalidArgumentException
     */
    public function test_invalid_string_Denominator($invalidStringTotalMark)
    {
        new TotalMarkWholeNumber($invalidStringTotalMark);
    }

    /**
     * @dataProvider provider_invalidRangeStringDenominators
     * @param string $totalMark
     * @expectedException RangeException
     */
    public function test_invalid_range_string_Denominator($totalMark)
    {
        new TotalMarkWholeNumber($totalMark);
    }

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

    public function provider_validStringDenominators()
    {
        return [
            ['1'],
            ['1.0'],
            ['20'],
            ['20.00000'],
            ['2000000'],
            ['2000000.00000'],
        ];
    }

    public function provider_invalidStringDenominators()
    {
        return [
            ['0.001'],
//            ['1x'],
//            ['1 x'],
            ['1.1'],
            ['1.1001'],
            ['1.01'],
            ['20.003'],
            ['20.000001'],
            ['2000000.4'],
            ['2000000.300000'],
            ['2000000.300000'],
        ];
    }

    public function provider_invalidRangeStringDenominators()
    {
        return [
            ['0'],        // Note, this means if rubric and total mark, rubric descriptors must have marks
        ];
    }

    public function provider_normalisedToHumanReadable()
    {
        $totalMark = 200;
        return [
            [-1,    $totalMark,      '0 / 200'],
            [0,     $totalMark,      '0 / 200'],
            [0.05,   $totalMark,     '0 / 200'],
            [0.1,   $totalMark,   '0.25 / 200'],
            [0.2,   $totalMark,    '0.5 / 200'],
            [0.3,   $totalMark,    '0.5 / 200'],
            [0.375,  $totalMark,  '0.75 / 200'],
            [01,    $totalMark,      '2 / 200'],
            [1.0,   $totalMark,      '2 / 200'],
            [99.4,  $totalMark, '198.75 / 200'],
            [99.7,  $totalMark,  '199.5 / 200'],
            [99.9,  $totalMark, '199.75 / 200'],
            [99.95,  $totalMark,   '200 / 200'],
            [100,   $totalMark,    '200 / 200'],
            [100.0, $totalMark,    '200 / 200'],
            [100.1, $totalMark,    '200 / 200'],
        ];
    }

}
