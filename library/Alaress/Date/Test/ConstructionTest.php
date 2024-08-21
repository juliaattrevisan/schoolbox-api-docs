<?php

/**
 * Test for verifying that various constructors and static builders work as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use Alaress_Date_Locale_Static;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConstructionTest extends TestCase
{
    use MockDateObject;

    public function setup(): void
    {
        if (!isset($_SERVER['REQUEST_TIME'])) {
            $this->markTestSkipped('No $_SERVER[REQUEST_TIME], cannot accurately test Alaress_Date_Format builders');
        }
    }

    public function testNow()
    {
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $this->assertInstanceOf(Alaress_Date_Format::class, $date);
    }

    public function testCreateFromAlaressDateFormat()
    {
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $copiedDate = self::buildDateObject($date);

        $this->assertEquals($copiedDate->getDatabase(), $date->getDatabase());
    }

    public function testInvalidCreate()
    {
        $this->expectException(InvalidArgumentException::class);
        self::buildDateObject('2015-03-25');
    }

    public function testCreateFromDateTime()
    {
        $date = DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME']);
        $copiedDate = self::buildDateObject($date);
        $this->assertEquals($copiedDate->getDatabase(), $date->format(Alaress_Date_Format::DATABASE_FORMAT));
    }

    public function testCreateFromAlaressDateFormatWithTimezone()
    {
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $date->setTimezone(new DateTimeZone('UTC'));
        $copiedDate = self::buildDateObject($date, null, new DateTimeZone('Australia/Victoria'));
        $this->assertEquals($copiedDate->getUnixTimeStamp(), $date->getUnixTimeStamp());
        $this->assertEquals('UTC', $date->getTimezone()->getName());
        $this->assertEquals('Australia/Victoria', $copiedDate->getTimezone()->getName());
    }

    public function testCreateFromDateTimeWithTimezone()
    {
        $date = DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME'], new DateTimeZone('UTC'));
        $copiedDate = self::buildDateObject($date, null, new DateTimeZone('Australia/Victoria'));
        $this->assertEquals($copiedDate->getUnixTimeStamp(), $date->getTimestamp());
        $this->assertEquals('Australia/Victoria', $copiedDate->getTimezone()->getName());
    }

    public function testNewLocal()
    {
        $date = Alaress_Date_Format::newLocal(
            '25/03/2015 8:15pm',
            new DateTimeZone('Australia/Victoria'),
            new Alaress_Date_Locale_Static()
        );
        $this->assertEquals('2015-03-25T20:15:00+11:00', $date->toIso8601String());
    }

    public function testNewLocalShort()
    {
        $date = Alaress_Date_Format::newLocalShort(
            '25/03/2015',
            new DateTimeZone('Australia/Victoria'),
            new Alaress_Date_Locale_Static()
        );
        $this->assertEquals('2015-03-25T00:00:00+11:00', $date->toIso8601String());
    }

    public function testClone()
    {
        $date = Alaress_Date_Format::newLocalShort(
            '25/03/2015',
            new DateTimeZone('UTC'),
            new Alaress_Date_Locale_Static()
        );
        $clone = clone $date;
        $clone = $clone->addDays(1);
        // Ensure clone doesn't affect original date
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
        $this->assertEquals('2015-03-26', $clone->getDatabaseDate());
    }

    /**
     * Ensures that a date can be built properly from multiple formats.
     */
    public function testMultipleDateFormatsFirstMatches()
    {
        $date = self::buildDateObject('25/03/2015', ['d/m/Y', 'Y-m-d']);
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
    }

    /**
     * Ensures that a date can be built properly from multiple formats.
     */
    public function testMultipleDateFormatsLastMatches()
    {
        $date = self::buildDateObject('2015-03-25', ['d/m/Y', 'Y-m-d']);
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
    }

    /**
     * Ensures that a date errors out if no format matches.
     *
     *
     */
    public function testMultipleDateFormatsNoneMatches()
    {
        $this->expectException(InvalidArgumentException::class);
        self::buildDateObject('2015-03-25', ['d/m/Y', 'Ymd']);
    }

    /**
     * Test trim of date input
     */
    public function testTrimDateTime()
    {
        $date = self::buildDateObject(' 2015-03-25 ', 'Y-m-d');
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
    }

    /**
     * Test trim of a single date format
     */
    public function testTrimDateFormat()
    {
        $date = self::buildDateObject('2015-03-25', ' Y-m-d ');
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
    }

    /**
     * Test trim of multiple date formats
     */
    public function testTrimMultipleDateFormats()
    {
        $date = self::buildDateObject('25/03/2015', [' d/m/Y ', ' Y-m-d ']);
        $this->assertEquals('2015-03-25', $date->getDatabaseDate());
    }
}
