<?php

/**
 * Tests for verifying that a date is a particular type.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use Alaress_Date_Locale_Static;
use PHPUnit\Framework\TestCase;

class IsATypeOfDateTest extends TestCase
{
    use MockDateObject;

    public static function getIsWeekdayData()
    {
        return [
            [self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT), true,],
            [self::buildDateObject('2015-03-22 12:34:54', Alaress_Date_Format::DATABASE_FORMAT), false,],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isWeekday
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getIsWeekdayData')]
    public function testIsWeekday($date, $isWeekday)
    {
        $this->assertEquals($isWeekday, $date->isWeekday());
        $this->assertNotEquals($isWeekday, $date->isWeekend());
    }

    public static function getIsLeapYearData()
    {
        return [
            // Leap years: every 4 years
            [self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT), false,],
            [self::buildDateObject('2016-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT), true,],
            // Special rule: excluding every 500th year
            [self::buildDateObject('1500-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT), false,],
            // Super special rule: but including every 2000th year
            [self::buildDateObject('2000-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT), true,],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isLeapYear
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getIsLeapYearData')]
    public function testIsLeapYear($date, $isLeapYear)
    {
        $this->assertEquals($isLeapYear, $date->isLeapYear());
    }


    public static function getBetweenData()
    {
        return [
            [
                self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                self::buildDateObject('2015-03-25 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                true,
                true,
            ],
            [
                self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                self::buildDateObject('2015-03-25 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                false,
                false,
            ],
            [
                self::buildDateObject('2015-03-25 12:34:53', Alaress_Date_Format::DATABASE_FORMAT),
                self::buildDateObject('2015-03-25 12:34:54', Alaress_Date_Format::DATABASE_FORMAT),
                self::buildDateObject('2015-03-25 12:34:55', Alaress_Date_Format::DATABASE_FORMAT),
                true,
                false,
            ],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param Alaress_Date_Format $start
     * @param Alaress_Date_Format $end
     * @param bool                $equal
     * @param bool                $result
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getBetweenData')]
    public function testBetween($date, $start, $end, $equal, $result)
    {
        $this->assertEquals($result, $date->between($start, $end, $equal));
    }

    public function testComparators()
    {
        $now = Alaress_Date_Format::now();
        $now->setLocale(new Alaress_Date_Locale_Static());
        $now->endOfDay();

        $date = self::buildDateObject($now->getUnixTimeStamp(), Alaress_Date_Format::TIMESTAMP);
        $this->assertTrue($date->isToday());
        $this->assertFalse($date->isYesterday());
        $this->assertFalse($date->isTomorrow());

        // Compare with another date
        $this->assertTrue($date->isSameDay($now));
        $this->assertFalse($date->eq($now)); // Because of millisecond precision
        $this->assertTrue($date->ne($now));
        $this->assertFalse($date->gt($now));
        $this->assertFalse($date->gte($now));
        $this->assertTrue($date->lt($now));
        $this->assertTrue($date->lte($now));

        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $date->addDays(1);
        $this->assertFalse($date->isToday());
        $this->assertFalse($date->isYesterday());
        $this->assertTrue($date->isTomorrow());
        $this->assertTrue($date->isFuture());
        $this->assertFalse($date->isPast());

        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $date->addDays(-1);
        $this->assertFalse($date->isToday());
        $this->assertTrue($date->isYesterday());
        $this->assertFalse($date->isTomorrow());
        $this->assertFalse($date->isFuture());
        $this->assertTrue($date->isPast());

        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $date->addDays(2);
        $this->assertFalse($date->isToday());
        $this->assertFalse($date->isYesterday());
        $this->assertFalse($date->isTomorrow());
        $this->assertTrue($date->isFuture());
        $this->assertFalse($date->isPast());

        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $date->addDays(-2);
        $this->assertFalse($date->isToday());
        $this->assertFalse($date->isYesterday());
        $this->assertFalse($date->isTomorrow());
        $this->assertFalse($date->isFuture());
        $this->assertTrue($date->isPast());
    }
}
