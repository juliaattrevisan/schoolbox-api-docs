<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\MarkScheme\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\MarkScheme\Percentage;
use \RangeException;

class PercentageTest extends TestCase
{

    /**
     * @dataProvider provider_normalisedToHumanReadable
     * @param float  $normalised
     * @param string $expected
     * @throws RangeException
     * @throws \InvalidArgumentException
     */
    public function test_getHumanReadable_Valid($normalised, $expected)
    {
        $markScheme = new Percentage();
        $actual = $markScheme->getHumanReadableMark($normalised);
        $message = "Failed asserting equality. Input: $normalised, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);

    }


    /**
     * @dataProvider provider_humanReadableToNormalised
     * @param string $humanReadable
     * @param float  $expected
     * @throws RangeException
     * @throws \InvalidArgumentException
     */
    public function test_getNormalised_Valid($humanReadable, $expected)
    {
        $markScheme = new Percentage();
        $actual = $markScheme->getNormalisedMark($humanReadable);
        $message = "Failed asserting equality. Input: $humanReadable, Expected: $expected, Actual: $actual";
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * @expectedException RangeException
     */
    public function test_getNormalised_TooLow()
    {
        $humanReadable = '-0.0001 %';
        $markScheme = new Percentage();
        $markScheme->getNormalisedMark($humanReadable);
    }


    /**
     * @expectedException RangeException
     */
    public function test_getNormalised_TooHigh()
    {
        $humanReadable = '100.0000001 %';
        $markScheme = new Percentage();
        $markScheme->getNormalisedMark($humanReadable);
    }

    // ==================== Data Providers ====================

    /**
     * @return array
     */
    public function provider_humanReadableToNormalised(): array
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
    public function provider_normalisedToHumanReadable(): array
    {
        return [
            [-0.0001,     '0 %'],
            [0,           '0 %'],
            [0.0,         '0 %'],
            [0.000000000, '0 %'],
            [0.000000001, '0 %'],
            [0.5,         '1 %'],
            [0.9,         '1 %'],
            [1,           '1 %'],
            [1.0,         '1 %'],
            [1.000000001, '1 %'],
            [10,          '10 %'],
            [10.00000000, '10 %'],
            [99,          '99 %'],
            [99.49999999, '99 %'],
            [99.5,        '100 %'],
            [100,         '100 %'],
            [100.0,       '100 %'],
            [100.0000001, '100 %'],
        ];
    }

}
