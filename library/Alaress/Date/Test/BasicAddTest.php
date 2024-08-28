<?php

/**
 * Test for verifying that add methods work as expected.
 *
 * Currently assumes that date objects are mutable.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;

class BasicAddTest extends TestCase
{
    use MockDateObject;

    protected function getTestDateObject()
    {
        return self::buildDateObject('2015-03-25 08:01:13', Alaress_Date_Format::DATABASE_FORMAT);
    }

    public function testAddSeconds()
    {
        $date = $this->getTestDateObject();
        $date->addSeconds(63);
        $this->assertEquals($date->getDatabase(), '2015-03-25 08:02:16');
    }

    public function testSubtractSeconds()
    {
        $date = $this->getTestDateObject();
        $date->addSeconds(-119);
        $this->assertEquals($date->getDatabase(), '2015-03-25 07:59:14');
    }

    public function testAddMinutes()
    {
        $date = $this->getTestDateObject();
        $date->addMinutes(88);
        $this->assertEquals($date->getDatabase(), '2015-03-25 09:29:13');
    }

    public function testSubtractMinutes()
    {
        $date = $this->getTestDateObject();
        $date->addMinutes(-71);
        $this->assertEquals($date->getDatabase(), '2015-03-25 06:50:13');
    }

    public function testAddHours()
    {
        $date = $this->getTestDateObject();
        $date->addHours(49);
        $this->assertEquals($date->getDatabase(), '2015-03-27 09:01:13');
    }

    public function testSubtractHours()
    {
        $date = $this->getTestDateObject();
        $date->addHours(-197);
        $this->assertEquals($date->getDatabase(), '2015-03-17 03:01:13');
    }

    public function testAddDays()
    {
        $date = $this->getTestDateObject();
        $date->addDays(21);
        $this->assertEquals($date->getDatabase(), '2015-04-15 08:01:13');
    }

    public function testSubtractDays()
    {
        $date = $this->getTestDateObject();
        $date->addDays(-21);
        $this->assertEquals($date->getDatabase(), '2015-03-04 08:01:13');
    }

    public function testAddWeeks()
    {
        $date = $this->getTestDateObject();
        $date->addWeeks(12);
        $this->assertEquals($date->getDatabase(), '2015-06-17 08:01:13');
    }

    public function testSubtractWeeks()
    {
        $date = $this->getTestDateObject();
        $date->addWeeks(-12);
        $this->assertEquals($date->getDatabase(), '2014-12-31 08:01:13');
    }

    public function testAddMonths()
    {
        $date = $this->getTestDateObject();
        $date->addMonths(14);
        $this->assertEquals($date->getDatabase(), '2016-05-25 08:01:13');
    }

    public function testSubtractMonths()
    {
        $date = $this->getTestDateObject();
        $date->addMonths(-14);
        $this->assertEquals($date->getDatabase(), '2014-01-25 08:01:13');
    }

    public function testAddYears()
    {
        $date = $this->getTestDateObject();
        $date->addYears(33);
        $this->assertEquals($date->getDatabase(), '2048-03-25 08:01:13');
    }

    public function testSubtractYears()
    {
        $date = $this->getTestDateObject();
        $date->addYears(-20);
        $this->assertEquals($date->getDatabase(), '1995-03-25 08:01:13');
    }
}
