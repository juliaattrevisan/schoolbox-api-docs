<?php

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use DateTime;
use PHPUnit\Framework\TestCase;

class LaravelDatetimeAlaressDateRelativeTest extends TestCase
{
    use MockDateObject;

    public function testRelativeTimePastSeconds1()
    {
        $input = '2011-08-31 12:12:00';
        $expected = '10 seconds before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:12:10'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastSeconds2()
    {
        $input = '2011-08-31 12:12:01';
        $expected = '54 seconds before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:12:55'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastMinutes1()
    {
        $input = '2011-08-31 12:12:01';
        $expected = '1 minute before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:13:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastMinutes2()
    {
        $input = '2011-08-31 12:12:01';
        $expected = '2 minutes before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:14:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastHours1()
    {
        $input = '2011-08-31 13:12:01';
        $expected = '1 hour before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 14:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastHours2()
    {
        $input = '2011-08-31 13:12:01';
        $expected = '5 hours before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 18:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastDays1()
    {
        $this->markTestSkipped('Yesterday no longer shows as "yesterday"');
        $input = '2011-08-30 13:12:01';
        $expected = 'Yesterday';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 13:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastDays2()
    {
        $input = '2011-08-26 13:12:01';
        $expected = '5 days before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 13:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastWeeks1()
    {
        $this->markTestSkipped('Last week no longer shows as "Last week"');
        $input = '2011-08-23 05:00:14';
        $expected = 'Last week';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastWeeks2()
    {
        $input = '2011-08-08 05:00:14';
        $expected = '3 weeks before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePastMonths1()
    {
        $input = '2011-06-08 05:00:14';
        $expected = '2 months before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-08 17:40:11'));

        $this->assertEquals($expected, $result);
    }

    /**
     * Expected output is in Australian date format
     *
     * @return void
     */
    public function testRelativeTimePastAncientHistory()
    {
        $input = '2001-01-03 02:02:12';
        $expected = '10 years before';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimePresent()
    {
        $this->markTestSkipped('Now no longer shows as "just then"');
        $input = '2011-06-08 05:01:01';
        $expected = 'just then';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime($input));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureSeconds1()
    {
        $input = '2011-06-08 05:01:03';
        $expected = '2 seconds after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureSeconds2()
    {
        $input = '2011-06-08 05:02:00';
        $expected = '59 seconds after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureMinutes1()
    {
        $input = '2011-06-08 05:02:05';
        $expected = '1 minute after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureMinutes2()
    {
        $input = '2011-06-08 05:55:00';
        $expected = '53 minutes after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureHours1()
    {
        $input = '2011-06-08 06:10:00';
        $expected = '1 hour after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureHours2()
    {
        $input = '2011-06-09 04:30:00';
        $expected = '23 hours after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureDays1()
    {
        $this->markTestSkipped('Tomorrow no longer shows as "tomorrow"');
        $input = '2011-06-09 06:01:03';
        $expected = 'Tomorrow';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureDays2()
    {
        $this->markTestSkipped('Future dates no longer show as "next weekday"');
        $input = '2011-06-10 10:01:01';
        $dayofweek = date('l', strtotime($input));
        $expected = 'on ' . $dayofweek;

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureDays3()
    {
        $this->markTestSkipped('Future dates no longer show as "next weekday"');
        $input = '2011-06-17 10:01:01';
        $dayofweek = date('l', strtotime($input));
        $expected = 'next ' . $dayofweek;

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureWeeks1()
    {
        $input = '2011-06-22 05:01:01';
        $expected = '2 weeks after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeFutureWeeks2()
    {
        $input = '2011-07-09 05:01:02';
        $expected = '1 month after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    /**
     * A future date more than 4 weeks will display in months
     */
    public function testRelativeTimeFutureMonths1()
    {
        $input = '2011-03-05 05:01:01';
        $expected = '1 month after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-02-01 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    /**
     * A future date between 1 and 11 months will display in months
     */
    public function testRelativeTimeFutureMonths2()
    {
        $input = '2012-01-10 05:01:01';
        $expected = '11 months after';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-02-01 05:01:01'));

        $this->assertEquals($expected, $result);
    }

    /**
     * A future date between 1 and 11 months will display in months
     */
    public function testRelativeTimeDystopia()
    {
        $this->markTestSkipped('I think Carbon gets this wrong, it returns 202 years not 201');
        $input = '2211-06-10 10:01:01';
        $expected = 'on 10th Jun 2211';

        $relativeDate = self::buildDateObject(new DateTime($input));
        $result = $relativeDate->getRelativeTime(new DateTime('2011-06-08 05:01:01'));

        $this->assertEquals($expected, $result);
    }
}
