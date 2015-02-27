<?php

class LaravelTimestampAlaressDateRelativeTest extends PHPUnit_Framework_TestCase
{

    /**
     * Skip these tests for Alaress_Date_Strategy_LaravelDate
     */
    protected function setUp()
    {
        $date = new Alaress_Date_Format();
        if (!$date->getDateTimeObject() instanceof \Carbon\Carbon) {
            $this->markTestSkipped('This test only applies to Alaress_Date_Strategy_LaravelDate');
        }
    }

    public function testRelativeTimeSeconds1()
    {
        $input    = date('U', strtotime('2011-08-31 12:12:00'));
        $expected = '10 seconds before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:12:10'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeSeconds2()
    {
        $input    = date('U', strtotime('2011-08-31 12:12:01'));
        $expected = '54 seconds before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:12:55'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeMinutes1()
    {
        $input    = date('U', strtotime('2011-08-31 12:12:01'));
        $expected = '1 minute before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:13:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeMinutes2()
    {
        $input    = date('U', strtotime('2011-08-31 12:12:01'));
        $expected = '2 minutes before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 12:14:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeHours1()
    {
        $input    = date('U', strtotime('2011-08-31 13:12:01'));
        $expected = '1 hour before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 14:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeHours2()
    {
        $input    = date('U', strtotime('2011-08-31 13:12:01'));
        $expected = '5 hours before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 18:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeDays1()
    {
        $this->markTestSkipped('Yesterday no longer shows as "yesterday"');
        $input    = date('U', strtotime('2011-08-30 13:12:01'));
        $expected = 'Yesterday';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 13:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeDays2()
    {
        $input    = date('U', strtotime('2011-08-26 13:12:01'));
        $expected = '5 days before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 13:12:01'));

        $this->assertEquals($expected, $result);
    }

    public function testRelativeTimeWeeks1()
    {
        $input    = date('U', strtotime('2011-08-08 05:00:14'));
        $expected = '3 weeks before';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }

    /**
     * Expected output is in Australian date format
     *
     * @return void
     */
    public function testRelativeTimeAncientHistory()
    {
        $input    = date('U', strtotime('2001-01-03 02:02:12'));
        $expected = 'on 3rd Jan 2001';

        $relativeDate = new Alaress_Date_Format($input, 'U');
        $result       = $relativeDate->getRelativeTime(new DateTime('2011-08-31 17:40:11'));

        $this->assertEquals($expected, $result);
    }
}
