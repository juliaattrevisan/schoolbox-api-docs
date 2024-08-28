<?php

/**
 * Tests for verifying that checks that a date is at the start of a timespan work as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;

class IsAtStartOrEndTest extends TestCase
{
    use MockDateObject;

    /**
     * @return Alaress_Date_Format
     */
    public function getTestDateObject()
    {
        return self::buildDateObject('2015-03-25 12:34:56', Alaress_Date_Format::DATABASE_FORMAT);
    }

    public function testStartOfDay()
    {
        $date = $this->getTestDateObject()->startOfDay();
        $this->assertEquals('00:00:00', $date->getDatabaseTime());
    }

    public function testEndOfDay()
    {
        $date = $this->getTestDateObject()->endOfDay();
        $this->assertEquals('23:59:59', $date->getDatabaseTime());
    }

    public function testStartOfWeek()
    {
        $date = $this->getTestDateObject()->startOfWeek();
        $this->assertEquals('2015-03-23 00:00:00', $date->getDatabase());
    }

    public function testEndOfWeek()
    {
        $date = $this->getTestDateObject()->endOfWeek();
        $this->assertEquals('2015-03-29 23:59:59', $date->getDatabase());
    }

    public function testStartOfMonth()
    {
        $date = $this->getTestDateObject()->startOfMonth();
        $this->assertEquals('2015-03-01 00:00:00', $date->getDatabase());
    }

    public function testEndOfMonth()
    {
        $date = $this->getTestDateObject()->endOfMonth();
        $this->assertEquals('2015-03-31 23:59:59', $date->getDatabase());
    }

    public function testStartOfYear()
    {
        $date = $this->getTestDateObject()->startOfYear();
        $this->assertEquals('2015-01-01 00:00:00', $date->getDatabase());
    }

    public function testEndOfYear()
    {
        $date = $this->getTestDateObject()->endOfYear();
        $this->assertEquals('2015-12-31 23:59:59', $date->getDatabase());
    }

    public function testIsAtStartOfDay()
    {
        $date = $this->getTestDateObject();
        $this->assertFalse($date->isAtStartOfDay());
        $date->startOfDay();
        $this->assertTrue($date->isAtStartOfDay());
    }

    public function testIsAtEndOfDay()
    {
        $date = $this->getTestDateObject();
        $this->assertFalse($date->isAtEndOfDay());
        $date->endOfDay();
        $this->assertTrue($date->isAtEndOfDay());
    }
}
