<?php

/**
 * Unit tests for Alaress\Date\Interval class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Date\Interval;
use DateInterval;
use DateTime;
use PHPUnit\Framework\TestCase;

class IntervalTest extends TestCase
{
    /**
     * @return array
     */
    public static function getIsInvertedData()
    {
        return [
            [new Interval(-1), true],
            [new Interval(0), false],
            [new Interval(1), false],
            [new Interval(PHP_INT_MAX), false],
            // Overflow to PHP_INT_MIN
            [new Interval((int)(PHP_INT_MAX + 1)), true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getIsInvertedData')]
    public function testIsInverted(Interval $interval, $isInverted)
    {
        $this->assertSame($isInverted, $interval->isInverted());
    }

    /**
     * @return array
     */
    public static function getSecondTotalData()
    {
        return [
            [new Interval(-1), 1],
            [new Interval(0), 0],
            [new Interval(59), 59],
            [new Interval(60), 60],
            [new Interval(61), 61],
            [new Interval(3600), 3600],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getSecondTotalData')]
    public function testSecondTotal(Interval $interval, $secondTotal)
    {
        $this->assertSame($secondTotal, $interval->getSecondTotal());
    }

    /**
     * @return array
     */
    public static function getSecondRemainderData()
    {
        return [
            [new Interval(-1), 1],
            [new Interval(0), 0],
            [new Interval(59), 59],
            [new Interval(60), 0],
            [new Interval(61), 1],
            [new Interval(3600), 0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getSecondRemainderData')]
    public function testSecondRemainder(Interval $interval, $secondRemainder)
    {
        $this->assertSame($secondRemainder, $interval->getSecondRemainder());
    }


    /**
     * @return array
     */
    public static function getMinuteTotalData()
    {
        return [
            [new Interval(-1), 0],
            [new Interval(0), 0],
            [new Interval(59), 0],
            [new Interval(60), 1],
            [new Interval(61), 1],
            [new Interval(3599), 59],
            [new Interval(3600), 60],
            [new Interval(3601), 60],
            [new Interval(7199), 119],
            [new Interval(7200), 120],
            [new Interval(7201), 120],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getMinuteTotalData')]
    public function testMinuteTotal(Interval $interval, $minuteTotal)
    {
        $this->assertSame($minuteTotal, $interval->getMinuteTotal());
    }

    /**
     * @return array
     */
    public static function getMinuteRemainderData()
    {
        return [
            [new Interval(-1), 0],
            [new Interval(0), 0],
            [new Interval(59), 0],
            [new Interval(60), 1],
            [new Interval(61), 1],
            [new Interval(3599), 59],
            [new Interval(3600), 0],
            [new Interval(3601), 0],
            [new Interval(7199), 59],
            [new Interval(7200), 0],
            [new Interval(7201), 0],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getMinuteRemainderData')]
    public function testMinuteRemainder(Interval $interval, $minuteRemainder)
    {
        $this->assertSame($minuteRemainder, $interval->getMinuteRemainder());
    }

    /**
     * @return array
     */
    public static function getHourTotalData()
    {
        return [
            [new Interval(-1), 0],
            [new Interval(0), 0],
            [new Interval(3599), 0],
            [new Interval(3600), 1],
            [new Interval(3601), 1],
            [new Interval(7199), 1],
            [new Interval(7200), 2],
            [new Interval(7201), 2],
            [new Interval(24 * 3600 - 1), 23],
            [new Interval(24 * 3600), 24],
            [new Interval(24 * 3600 + 1), 24],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getHourTotalData')]
    public function testHourTotal(Interval $interval, $hourTotal)
    {
        $this->assertSame($hourTotal, $interval->getHourTotal());
    }

    /**
     * @return array
     */
    public static function getHourRemainderData()
    {
        return [
            [new Interval(-1), 0],
            [new Interval(0), 0],
            [new Interval(3599), 0],
            [new Interval(3600), 1],
            [new Interval(3601), 1],
            [new Interval(7199), 1],
            [new Interval(7200), 2],
            [new Interval(7201), 2],
            [new Interval(24 * 3600 - 1), 23],
            [new Interval(24 * 3600), 0],
            [new Interval(24 * 3600 + 1), 0],
            [new Interval(25 * 3600 - 1), 0],
            [new Interval(25 * 3600), 1],
            [new Interval(25 * 3600 + 1), 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getHourRemainderData')]
    public function testHourRemainder(Interval $interval, $hourRemainder)
    {
        $this->assertSame($hourRemainder, $interval->getHourRemainder());
    }

    /**
     * @return array
     */
    public static function getDayTotalData()
    {
        return [
            [new Interval(-1), 0],
            [new Interval(0), 0],
            [new Interval(24 * 3600 - 1), 0],
            [new Interval(24 * 3600), 1],
            [new Interval(24 * 3600 + 1), 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getDayTotalData')]
    public function testDayTotal(Interval $interval, $dayTotal)
    {
        $this->assertSame($dayTotal, $interval->getDayTotal());
    }

    /**
     * @return array
     */
    public static function getCreateFromDateIntervalData()
    {
        return [
            [new DateInterval('PT6H8M30S'), 22110],
            [new DateInterval('P3D'), 259200],
            [new DateInterval('P1M'), 30 * 24 * 60 * 60],
            [new DateInterval('P1Y'), 365 * 24 * 60 * 60],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCreateFromDateIntervalData')]
    public function testCreateFromDateInterval(DateInterval $interval, $secondTotal)
    {
        $this->assertSame($secondTotal, Interval::createFromDateInterval($interval)->getSecondTotal());
    }

    /**
     * @return array
     */
    public static function getCreateFromDateTimeDifferenceData()
    {
        return [
            [new DateTime("2014-03-16T22:29:35Z"), new DateTime("2014-03-17T22:29:35Z"), 86400],
            [new DateTime("2014-03-17T22:29:35Z"), new DateTime("2014-03-17T22:29:36Z"), 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getCreateFromDateTimeDifferenceData')]
    public function testCreateFromDateTimeDifference(DateTime $from, DateTime $to, $secondTotal)
    {
        $this->assertSame(
            $secondTotal,
            Interval::createFromDateTimeDifference($from, $to)->getSecondTotal()
        );
    }

    /**
     * @return array
     */
    public static function getFormatData()
    {
        return [
            [new Interval(0), '%S seconds', '0 seconds'],
            [new Interval(0), '%s seconds', '0 seconds'],
            [new Interval(0), '%02s seconds', '00 seconds'],
            [new Interval(59), '%S seconds', '59 seconds'],
            [new Interval(59), '%s seconds', '59 seconds'],
            [new Interval(59), '%02s seconds', '59 seconds'],
            [new Interval(60), '%S seconds', '60 seconds'],
            [new Interval(60), '%Im %ss', '1m 0s'],
            [new Interval(60), '%I:%02s', '1:00'],
            [new Interval(61), '%S seconds', '61 seconds'],
            [new Interval(61), '%Im %ss', '1m 1s'],
            [new Interval(61), '%I:%02s', '1:01'],
            [new Interval(61), '%H:%02i:%02s', '0:01:01'],

            [new Interval(3599), '%I minutes', '59 minutes'],
            [new Interval(3599), '%Im %ss', '59m 59s'],
            [new Interval(3599), '%I:%02s', '59:59'],
            [new Interval(3599), '%H:%02i:%02s', '0:59:59'],

            [new Interval(3600), '%I minutes', '60 minutes'],
            [new Interval(3600), '%Im %ss', '60m 0s'],
            [new Interval(3600), '%I:%02s', '60:00'],
            [new Interval(3600), '%H:%02i:%02s', '1:00:00'],

            [new Interval(3601), '%I minutes', '60 minutes'],
            [new Interval(3601), '%Im %ss', '60m 1s'],
            [new Interval(3601), '%I:%02s', '60:01'],
            [new Interval(3601), '%H:%02i:%02s', '1:00:01'],

            [new Interval(24 * 3600 - 1), '%H hours', '23 hours'],
            [new Interval(24 * 3600 - 1), '%I minutes', '1439 minutes'],
            [new Interval(24 * 3600 - 1), '%S seconds', '86399 seconds'],
            [new Interval(24 * 3600 - 1), '%Dd %hh %im %ss', '0d 23h 59m 59s'],
            [new Interval(24 * 3600 - 1), '%Dd %02h:%02i:%02s', '0d 23:59:59'],

            [new Interval(24 * 3600), '%H hours', '24 hours'],
            [new Interval(24 * 3600), '%I minutes', '1440 minutes'],
            [new Interval(24 * 3600), '%S seconds', '86400 seconds'],
            [new Interval(24 * 3600), '%Dd %hh %im %ss', '1d 0h 0m 0s'],
            [new Interval(24 * 3600), '%Dd %02h:%02i:%02s', '1d 00:00:00'],

            [new Interval(24 * 3600 + 1), '%H hours', '24 hours'],
            [new Interval(24 * 3600 + 1), '%I minutes', '1440 minutes'],
            [new Interval(24 * 3600 + 1), '%S seconds', '86401 seconds'],
            [new Interval(24 * 3600 + 1), '%Dd %hh %im %ss', '1d 0h 0m 1s'],
            [new Interval(24 * 3600 + 1), '%Dd %02h:%02i:%02s', '1d 00:00:01'],

            [new Interval(1), '%r%Ss', '1s'],
            [new Interval(1), '%R%Ss', '+1s'],
            [new Interval(-1), '%r%Ss', '-1s'],
            [new Interval(-1), '%R%Ss', '-1s'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getFormatData')]
    public function testFormat(Interval $interval, $format, $expectedOutput)
    {
        $this->assertSame($expectedOutput, $interval->format($format));
    }

    /**
     * @return array
     */
    public static function getJsonSerializeData()
    {
        return [
            [new Interval(1), 1],
            [new Interval(0), 0],
            [new Interval(-1), -1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('getJsonSerializeData')]
    public function testJsonSerialize(Interval $interval, $expectedOutput)
    {
        $this->assertSame($expectedOutput, $interval->jsonSerialize());
    }
}
