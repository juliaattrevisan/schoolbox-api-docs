<?php

/**
 * Test for verifying that built in formats work as expected.
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

class BuiltinFormatsTest extends TestCase
{
    use MockDateObject;

    // ADF internal formats

    public function testDatabaseFormat()
    {
        $date = self::buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT);
        $this->assertEquals('2015-03-25 00:00:00', $date->getDatabase());
    }

    public function testDatabaseDateFormat()
    {
        $date = self::buildDateObject('2015-03-25', Alaress_Date_Format::DATABASE_DATE_FORMAT);
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
    }

    public function testDatabaseTimeFormat()
    {
        $date = self::buildDateObject('15:37:20', Alaress_Date_Format::DATABASE_TIME_FORMAT);
        $this->assertEquals('15:37:20', $date->getDatabaseTime());
    }

    public function testTimestampFormat()
    {
        $date = self::buildDateObject(1234567890, Alaress_Date_Format::TIMESTAMP, new DateTimeZone('UTC'));
        $this->assertEquals(1234567890, $date->getUnixTimeStamp());
    }

    public function testUrlFormat()
    {
        $date = self::buildDateObject('25-03-2015', Alaress_Date_Format::URL_FORMAT);
        $this->assertEquals('25-03-2015', $date->getURLDate());
    }

    public function testToStringFormat()
    {
        $date = self::buildDateObject(
            '2015-03-25 12:34:56',
            Alaress_Date_Format::DATABASE_FORMAT,
            new DateTimeZone('UTC')
        );
        $this->assertEquals('2015-03-25T12:34:56+00:00', $date->__toString());
    }

    public function testDateTimeBuiltinFormats()
    {
        $testDate = self::buildDateObject(
            '2015-03-25 12:34:56',
            Alaress_Date_Format::DATABASE_FORMAT,
            new DateTimeZone('UTC')
        );
        $this->assertEquals('2015-03-25T12:34:56+00:00', $testDate->toAtomString());
        $this->assertEquals('Wednesday, 25-Mar-2015 12:34:56 UTC', $testDate->toCookieString());
        $this->assertEquals('2015-03-25T12:34:56+00:00', $testDate->toIso8601String());
        $this->assertEquals('Wed, 25 Mar 15 12:34:56 +0000', $testDate->toRfc822String());
        $this->assertEquals('Wednesday, 25-Mar-15 12:34:56 UTC', $testDate->toRfc850String());
        $this->assertEquals('Wed, 25 Mar 15 12:34:56 +0000', $testDate->toRfc1036String());
        $this->assertEquals('Wed, 25 Mar 2015 12:34:56 +0000', $testDate->toRfc1123String());
        $this->assertEquals('Wed, 25 Mar 2015 12:34:56 +0000', $testDate->toRfc2822String());
        $this->assertEquals('2015-03-25T12:34:56+00:00', $testDate->toRfc3339String());
        $this->assertEquals('Wed, 25 Mar 2015 12:34:56 +0000', $testDate->toRssString());
        $this->assertEquals('2015-03-25T12:34:56+00:00', $testDate->toW3cString());
    }
}
