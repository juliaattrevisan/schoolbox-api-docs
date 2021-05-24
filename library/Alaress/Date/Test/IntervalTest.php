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
use PHPUnit\Framework\Error\Warning;
use PHPUnit\Framework\TestCase;

class IntervalTest extends TestCase
{

    /**
     * @return array
     */
    public function getIsInvertedData()
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

    /**
     * @dataProvider getIsInvertedData
     */
    public function testIsInverted(Interval $interval, $isInverted)
    {
        $this->assertSame($isInverted, $interval->isInverted());
    }

    /**
     * @return array
     */
    public function getSecondTotalData()
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

    /**
     * @dataProvider getSecondTotalData
     */
    public function testSecondTotal(Interval $interval, $secondTotal)
    {
        $this->assertSame($secondTotal, $interval->getSecondTotal());
    }

    /**
     * @return array
     */
    public function getSecondRemainderData()
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

    /**
     * @dataProvider getSecondRemainderData
     */
    public function testSecondRemainder(Interval $interval, $secondRemainder)
    {
        $this->assertSame($secondRemainder, $interval->getSecondRemainder());
    }


    /**
     * @return array
     */
    public function getMinuteTotalData()
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

    /**
     * @dataProvider getMinuteTotalData
     */
    public function testMinuteTotal(Interval $interval, $minuteTotal)
    {
        $this->assertSame($minuteTotal, $interval->getMinuteTotal());
    }

    /**
     * @return array
     */
    public function getMinuteRemainderData()
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

    /**
     * @dataProvider getMinuteRemainderData
     */
    public function testMinuteRemainder(Interval $interval, $minuteRemainder)
    {
        $this->assertSame($minuteRemainder, $interval->getMinuteRemainder());
    }

    /**
     * @return array
     */
    public function getHourTotalData()
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

    /**
     * @dataProvider getHourTotalData
     */
    public function testHourTotal(Interval $interval, $hourTotal)
    {
        $this->assertSame($hourTotal, $interval->getHourTotal());
    }

    /**
     * @return array
     */
    public function getHourRemainderData()
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

    /**
     * @dataProvider getHourRemainderData
     */
    public function testHourRemainder(Interval $interval, $hourRemainder)
    {
        $this->assertSame($hourRemainder, $interval->getHourRemainder());
    }

    /**
     * @return array
     */
    public function getDayTotalData()
    {
        return [
            [new Interval(-1), 0],
            [new Interval(0), 0],
            [new Interval(24 * 3600 - 1), 0],
            [new Interval(24 * 3600), 1],
            [new Interval(24 * 3600 + 1), 1],
        ];
    }

    /**
     * @dataProvider getDayTotalData
     */
    public function testDayTotal(Interval $interval, $dayTotal)
    {
        $this->assertSame($dayTotal, $interval->getDayTotal());
    }

    /**
     * @return array
     */
    public function getCreateFromDateIntervalData()
    {
        return [
            [new DateInterval('PT6H8M30S'), 22110],
            [new DateInterval('P3D'), 259200],
        ];
    }

    /**
     * @dataProvider getCreateFromDateIntervalData
     */
    public function testCreateFromDateInterval(DateInterval $interval, $secondTotal)
    {
        $this->assertSame($secondTotal, Interval::createFromDateInterval($interval)->getSecondTotal());
    }

    public function testCreateFromDateIntervalWarningOnMonth()
    {
        $this->expectWarning();
        $interval = Interval::createFromDateInterval(new DateInterval('P1M'));
        $this->assertSame(30, $interval->getDayTotal());
    }

    public function testCreateFromDateIntervalWarningOnYear()
    {
        $this->expectWarning();
        $interval = Interval::createFromDateInterval(new DateInterval('P1Y'));
        $this->assertSame(365, $interval->getDayTotal());
    }

    /**
     * @return array
     */
    public function getCreateFromDateTimeDifferenceData()
    {
        return [
            [new DateTime("2014-03-16T22:29:35Z"), new DateTime("2014-03-17T22:29:35Z"), 86400],
            [new DateTime("2014-03-17T22:29:35Z"), new DateTime("2014-03-17T22:29:36Z"), 1],
        ];
    }

    /**
     * @dataProvider getCreateFromDateTimeDifferenceData
     */
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
    public function getFormatData()
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

    /**
     * @dataProvider getFormatData
     */
    public function testFormat(Interval $interval, $format, $expectedOutput)
    {
        $this->assertSame($expectedOutput, $interval->format($format));
    }

    /**
     * @return array
     */
    public function getJsonSerializeData()
    {
        return [
            [new Interval(1), 1],
            [new Interval(0), 0],
            [new Interval(-1), -1],
        ];
    }

    /**
     * @dataProvider getJsonSerializeData
     */
    public function testJsonSerialize(Interval $interval, $expectedOutput)
    {
        $this->assertSame($expectedOutput, $interval->jsonSerialize());
    }
}
