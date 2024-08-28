<?php

/**
 * Test for verifying that get and set of parts of time work as expected.
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

class BasicGetSetTest extends TestCase
{
    use MockDateObject;

    protected function getTestDateObject(DateTimeZone $timezone = null)
    {
        return self::buildDateObject('2015-03-25 08:01:13', Alaress_Date_Format::DATABASE_FORMAT, $timezone);
    }

    public function testGetSetSecond()
    {
        $date = $this->getTestDateObject();
        $date->setSecond(13);
        $this->assertEquals($date->getDatabase(), '2015-03-25 08:01:13');
    }

    public function testGetSetMinute()
    {
        $date = $this->getTestDateObject();
        $date->setMinute(8);
        $this->assertEquals($date->getDatabase(), '2015-03-25 08:08:13');
    }

    public function testGetSetHour()
    {
        $date = $this->getTestDateObject();
        $date->setHour(23);
        $this->assertEquals($date->getDatabase(), '2015-03-25 23:01:13');
    }

    public function testGetSetDay()
    {
        $date = $this->getTestDateObject();
        $date->setDay(2);
        $this->assertEquals($date->getDatabase(), '2015-03-02 08:01:13');
    }

    public function testGetSetMonth()
    {
        $date = $this->getTestDateObject();
        $date->setMonth(9);
        $this->assertEquals($date->getDatabase(), '2015-09-25 08:01:13');
    }

    public function testGetSetYear()
    {
        $date = $this->getTestDateObject();
        $date->setYear(1871);
        $this->assertEquals($date->getDatabase(), '1871-03-25 08:01:13');
    }

    public function testGetSetDate()
    {
        $date = $this->getTestDateObject();
        $date->setDate(3019, 12, 10);
        $this->assertEquals($date->getDatabase(), '3019-12-10 08:01:13');
    }

    public function testGetSetTime()
    {
        $date = $this->getTestDateObject();
        $date->setTime(15, 15, 35);
        $this->assertEquals($date->getDatabase(), '2015-03-25 15:15:35');
    }

    public function testGetSetDateTime()
    {
        $date = $this->getTestDateObject();
        $date->setDateTime(100, 11, 9, 1, 7, 47);
        $this->assertEquals($date->getDatabase(), '0100-11-09 01:07:47');
    }

    public function testGetSetTimestamp()
    {
        // a timezone is required to test timestamps reliably
        $date = self::buildDateObject(time(), 'U', new DateTimeZone('UTC'));
        $date->setTimestamp(1);
        $this->assertEquals($date->getDatabase(), '1970-01-01 00:00:01');
    }

    public function testGetSetTimezone()
    {
        // Doesn't test creating a date with a timezone yet, or test time
        // when changing a timezone.
        $date = $this->getTestDateObject(new DateTimeZone('UTC'));
        // QLD: GMT+10
        $date->setTimezone('Australia/Queensland');
        $this->assertEquals($date->getDatabase(), '2015-03-25 18:01:13');
        // VIC: GMT+11 (DST)
        $date->setTimezone('Australia/Victoria');
        $this->assertEquals($date->getDatabase(), '2015-03-25 19:01:13');
    }
}
