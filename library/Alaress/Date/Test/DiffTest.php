<?php

/**
 * Tests for verifying diffs.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class DiffTest extends TestCase
{
    use MockDateObject;

    public function getDiffInSecondsData()
    {
        return [
            [
                // 1 day - 1 second: 86399
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                86399,
            ],
            [
                // 1 day - 86400
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                86400,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInSecondsData')]
    public function testDiffInSeconds($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInSeconds($b));
        $this->assertEquals($expected * -1, $b->diffInSeconds($a, false));
    }

    public function getDiffInMinutesData()
    {
        return [
            [
                // 1 day - 1 second: 1439
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                1439,
            ],
            [
                // 1 day - 24
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                1440,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInMinutesData')]
    public function testDiffInMinutes($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInMinutes($b));
        $this->assertEquals($expected * -1, $b->diffInMinutes($a, false));
    }

    public function getDiffInHoursData()
    {
        return [
            [
                // 1 day - 1 second: 23
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                23,
            ],
            [
                // 1 day - 24
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                24,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInHoursData')]
    public function testDiffInHours($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInHours($b));
        $this->assertEquals($expected * -1, $b->diffInHours($a, false));
    }

    public function getDiffInDaysData()
    {
        return [
            [
                // 1 day - 1 second: 0
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                0,
            ],
            [
                // 1 day
                $this->buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-26 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                1,
            ],
            //            [
            //                // Different timezones, how do you handle that?
            //                $this->buildDateObject(
            //                    '2015-03-25 12:34:56',
            //                    Alaress_Date_Format::DATABASE_FORMAT,
            //                    new DateTimeZone('UTC')
            //                ),
            //                $this->buildDateObject(
            //                    '2015-03-25 12:34:56',
            //                    Alaress_Date_Format::DATABASE_FORMAT,
            //                    new DateTimeZone('Australia/Queensland')
            //                ),
            //                0,
            //            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInDaysData')]
    public function testDiffInDays($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInDays($b));
        $this->assertEquals($expected * -1, $b->diffInDays($a, false));
    }

    public function getDiffInWeekdaysData()
    {
        return [
            [
                // 7 days - 1 second: 5 weekdays, apparently
                // I think it should be 4 but whatever.
                $this->buildDateObject('2015-03-22 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-29 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                5,
            ],
            [
                // 7 days: 5 weekdays
                $this->buildDateObject('2015-03-23 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-30 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                5,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInWeekdaysData')]
    public function testDiffInWeekdays($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInWeekdays($b));
        $this->assertEquals($expected * -1, $b->diffInWeekdays($a, false));
    }

    public function getDiffInWeekendDaysData()
    {
        return [
            [
                // 7 days - 1 second: 2 weekend days, apparently
                // I think it should be 1 because this doesn't span 2 whole weekend days,
                // but whatever.
                $this->buildDateObject('2015-03-22 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-29 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                2,
            ],
            [
                // 7 days: 2 weekend days
                $this->buildDateObject('2015-03-23 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-30 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                2,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInWeekendDaysData')]
    public function testDiffInWeekendDays($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInWeekendDays($b));
        $this->assertEquals($expected * -1, $b->diffInWeekendDays($a, false));
    }

    public function getDiffInWeeksData()
    {
        return [
            [
                // 7 days - 1 second: 0 weeks
                $this->buildDateObject('2015-03-22 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-29 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                0,
            ],
            [
                // 7 days: 1 week
                $this->buildDateObject('2015-03-23 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-30 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                1,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInWeeksData')]
    public function testDiffInWeeks($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInWeeks($b));
        $this->assertEquals($expected * -1, $b->diffInWeeks($a, false));
    }

    public function getDiffInMonthsData()
    {
        return [
            [
                // 1 month - 1 second: 1 month, apparently
                // I think it should be 0 but i'm just testing the passthru
                $this->buildDateObject(
                    '2015-03-22 12:34:56',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('UTC')
                ),
                $this->buildDateObject(
                    '2015-04-22 12:34:55',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('UTC')
                ),
                0,
            ],
            [
                // 1 month
                $this->buildDateObject(
                    '2015-03-23 12:34:56',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('UTC')
                ),
                $this->buildDateObject(
                    '2015-04-23 12:34:56',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('UTC')
                ),
                1,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInMonthsData')]
    public function testDiffInMonths($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInMonths($b));
        $this->assertEquals($expected * -1, $b->diffInMonths($a, false));
    }

    public function getDiffInYearsData()
    {
        return [
            [
                // 1 year - 1 second: 0 years
                // I think this is right but inconsistent with getDiffInMonths, but w/e
                $this->buildDateObject('2015-03-22 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2016-03-22 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                0,
            ],
            [
                // 1 year
                $this->buildDateObject('2015-03-23 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2016-03-23 12:34:56', Alaress_Date_Format::DATABASE_FORMAT),
                1,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getDiffInYearsData')]
    public function testDiffInYears($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->diffInYears($b));
        $this->assertEquals($expected * -1, $b->diffInYears($a, false));
    }

    public function getCompareData()
    {
        return [
            [
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                0,
            ],
            [
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-22 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                -1,
            ],
            [
                // Date 2's date is greater, but date 1 is greater because of timezone.
                $this->buildDateObject(
                    '2015-03-21 23:59:59',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('UTC')
                ),
                $this->buildDateObject(
                    '2015-03-22 00:00:00',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('Australia/Queensland')
                ),
                1,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getCompareData')]
    public function testCompare($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->compare($b));
        $this->assertEquals($expected * -1, $b->compare($a));
    }

    public function getCompareDateOnlyData()
    {
        return [
            [
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-22 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                0,
            ],
            [
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-23 12:34:52', Alaress_Date_Format::DATABASE_FORMAT),
                -1,
            ],
            [
                // Date 2's date is greater, but date 1 is greater because of timezone.
                // compareDateOnly ignores timezone completely, so date 1 < date 2.
                $this->buildDateObject(
                    '2015-03-21 23:59:59',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('UTC')
                ),
                $this->buildDateObject(
                    '2015-03-22 00:00:00',
                    Alaress_Date_Format::DATABASE_FORMAT,
                    new DateTimeZone('Australia/Queensland')
                ),
                -1,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getCompareDateOnlyData')]
    public function testCompareDateOnly($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->compareDateOnly($b));
        $this->assertEquals($expected * -1, $b->compareDateOnly($a));
    }


    public function getCompareYearOnlyData()
    {
        return [
            [
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-23 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                0,
            ],
            [
                $this->buildDateObject('2014-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                $this->buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                -1,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $a
     * @param Alaress_Date_Format $b
     * @param int                 $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getCompareYearOnlyData')]
    public function testCompareYearOnly($a, $b, $expected)
    {
        $this->assertEquals($expected, $a->compareYearOnly($b));
        $this->assertEquals($expected * -1, $b->compareYearOnly($a));
    }
}
