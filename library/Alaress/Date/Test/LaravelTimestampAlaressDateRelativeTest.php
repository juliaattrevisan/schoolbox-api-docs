<?php

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use DateTime;
use PHPUnit\Framework\TestCase;

class LaravelTimestampAlaressDateRelativeTest extends TestCase
{
    use MockDateObject;

    public function testRelativeTimeSeconds1()
    {
        $input = date('U', strtotime('2011-08-31 12:12:00'));
        $expected = '10 seconds before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:12:10'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeSeconds2()
    {
        $input = date('U', strtotime('2011-08-31 12:12:01'));
        $expected = '54 seconds before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:12:55'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeMinutes1()
    {
        $input = date('U', strtotime('2011-08-31 12:12:01'));
        $expected = '1 minute before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:13:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeMinutes2()
    {
        $input = date('U', strtotime('2011-08-31 12:12:01'));
        $expected = '2 minutes before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:14:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeHours1()
    {
        $input = date('U', strtotime('2011-08-31 13:12:01'));
        $expected = '1 hour before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 14:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeHours2()
    {
        $input = date('U', strtotime('2011-08-31 13:12:01'));
        $expected = '5 hours before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 18:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeDays1()
    {
        $this->markTestSkipped('Yesterday no longer shows as "yesterday"');
        $input = date('U', strtotime('2011-08-30 13:12:01'));
        $expected = 'Yesterday';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 13:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeDays2()
    {
        $input = date('U', strtotime('2011-08-26 13:12:01'));
        $expected = '5 days before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 13:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeWeeks1()
    {
        $input = date('U', strtotime('2011-08-08 05:00:14'));
        $expected = '3 weeks before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }

    /**
     * Expected output is in Australian date format
     *
     * @return void
     */
    public function testRelativeTimeAncientHistory()
    {
        $input = date('U', strtotime('2001-01-03 02:02:12'));
        $expected = '10 years before';

        $relativeDate = self::buildDateObject($input, 'U');
        $result = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }
}
