<?php

/**
 * Test for verifying that basic get parts of date methods work as expected.
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

class BasicGetPartTest extends TestCase
{
    use MockDateObject;

    protected function getTestDateObject(DateTimeZone $timezone = null)
    {
        return self::buildDateObject('2015-03-05 08:01:03', Alaress_Date_Format::DATABASE_FORMAT, $timezone);
    }

    public function testGetSecond()
    {
        $date = $this->getTestDateObject();
        $this->assertSame('3', $date->getSecond());
        $this->assertSame('03', $date->getSecond(true));
    }

    public function testGetMinute()
    {
        $date = $this->getTestDateObject();
        $this->assertSame('1', $date->getMinute());
        $this->assertSame('01', $date->getMinute(true));
    }

    public function testGetHour()
    {
        $date = $this->getTestDateObject();
        $this->assertSame('8', $date->getHour());
        $this->assertSame('08', $date->getHour(true));
    }

    public function testGetDay()
    {
        $date = $this->getTestDateObject();
        $this->assertSame('5', $date->getDay());
        $this->assertSame('05', $date->getDay(true));
    }

    public function testGetMonth()
    {
        $date = $this->getTestDateObject();
        $this->assertSame('3', $date->getMonth());
        $this->assertSame('03', $date->getMonth(true));
    }

    public function testGetYear()
    {
        $date = $this->getTestDateObject();
        $this->assertSame('2015', $date->getYear());
    }

    public function testGetWeekdayNumber()
    {
        $date = $this->getTestDateObject();
        $this->assertEquals(Alaress_Date_Format::THURSDAY, $date->getWeekDayNumber());
    }

    public function testGetTimezone()
    {
        $date = $this->getTestDateObject(new DateTimeZone('Australia/Queensland'));
        $this->assertEquals('Australia/Queensland', $date->getTimezone()->getName());
    }
}
