<?php

/**
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress_Date_Format;
use Alaress_Date_Locale_Config;
use PHPUnit\Framework\TestCase;
use Schoolbox\Config\Loader;

class RelativeDayTest extends TestCase
{
    /**
     * @var Alaress_Date_Locale_Config
     */
    protected $dateLocale;

    /**
     * Set up a mock config object.
     */
    public function setup(): void
    {
        $mockConfig = $this->createMock(Loader::class);
        $mockConfig
            ->expects($this->any())
            ->method('get')
            ->willReturnMap([
                ['date_database', 'Y-m-d H:i:s',],
                ['date_local', 'd/m/Y',],
                ['date_long', 'l F jS Y',],
                ['date_notime', 'jS M Y',],
                ['date_only', 'jS M',],
                ['date_short', 'jS M Y',],
                ['date_time', 'g:ia',],
                ['date_timezone', 'Australia/Victoria',],
            ]);
        $this->dateLocale = new Alaress_Date_Locale_Config();
        $this->dateLocale->setConfig($mockConfig);
    }


    /**
     * Check that an event in the same day displays 'Today'
     */
    public function testRelativeDayToday()
    {
        $expected = 'today at 2:00pm';

        $now = new Alaress_Date_Format('2000-01-01 00:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format('2000-01-01 14:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }


    /**
     * Check that an event the day before displays 'Yesterday'
     */
    public function testRelativeDayYesterday()
    {
        $now = new Alaress_Date_Format(null, null, null, $this->dateLocale);

        $expected = 'yesterday at ' . $now->getTimeOnly();

        $eventDate = new Alaress_Date_Format(null, null, null, $this->dateLocale);
        $eventDate->addDays(-1);

        $result = $eventDate->getRelativeDay($now);
        $this->assertEquals($expected, $result);
    }


    /**
     * Check that an event the day after displays 'Tomorrow'
     */
    public function testRelativeDayTomorrow()
    {
        $now = new Alaress_Date_Format(null, null, null, $this->dateLocale);

        $expected = 'tomorrow at ' . $now->getTimeOnly();

        $eventDate = new Alaress_Date_Format(null, null, null, $this->dateLocale);
        $eventDate->addDays(1);

        $result = $eventDate->getRelativeDay($now);
        $this->assertEquals($expected, $result);
    }


    /**
     * Check that an event in the past week displays 'last {day name}'
     */
    public function testRelativeDayWithinSevenPast()
    {
        $now = new Alaress_Date_Format(null, null, null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format(null, null, null, $this->dateLocale);
        $eventDate->addDays(-5);
        $expected = 'last ' . $eventDate->getUsingFormat('l') . ' at ' . $eventDate->getUsingFormat('g:ia');

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }


    /**
     * Check that an event happening within a week displays day name
     */
    public function testRelativeDayWithinSeven()
    {
        $now = new Alaress_Date_Format(null, null, null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format(null, null, null, $this->dateLocale);
        $eventDate->addDays(+5);
        $expected = $eventDate->getUsingFormat('l') . ' at ' . $eventDate->getUsingFormat('g:ia');

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }


    /**
     * Check that if the event date is the same day next week
     * (e.g. today is Wednesday and the event is next Wednesday..
     * that is displays the date rather than the day name)
     */
    public function testRelativeDayOneWeekPast()
    {
        $expected = '7th Jan 2017 at 11:00pm';

        $now = new Alaress_Date_Format('2017-01-14 16:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format('2017-01-07 23:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }


    /**
     * Check the same as above but the event is the future rather than past
     */
    public function testRelativeDayOneWeek()
    {
        $expected = '8th Jan 2017 at 11:00am';

        $now = new Alaress_Date_Format('2017-01-01 16:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format('2017-01-08 11:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }


    /**
     * Check that a date beyond 30 days in the past just displays date, no time
     */
    public function testRelativeDayBeyondThirtyPast()
    {
        $expected = '1st Jan 2000';

        $now = new Alaress_Date_Format('2000-02-02 16:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format('2000-01-01 11:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }

    /**
     * Check that a date beyond 30 days in the past just displays date, no time
     */
    public function testRelativeDayBeyondThirty()
    {
        $expected = '2nd Feb 2000';

        $now = new Alaress_Date_Format('2000-01-01 16:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);
        $eventDate = new Alaress_Date_Format('2000-02-02 11:00:00', 'Y-m-d H:i:s', null, $this->dateLocale);

        $result = $eventDate->getRelativeDay($now);

        $this->assertEquals($expected, $result);
    }
}
