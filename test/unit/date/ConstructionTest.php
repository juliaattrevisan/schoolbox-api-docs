<?php
/**
 * Test for verifying that various constructors and static builders work as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

class ConstructionTest extends PHPUnit_Framework_TestCase
{
    use Alaress\Test\Date\MockDateObject;

    public function setup()
    {
        if (!isset($_SERVER['REQUEST_TIME'])) {
            $this->markTestSkipped('No $_SERVER[REQUEST_TIME], cannot accurately test Alaress_Date_Format builders');
        }
    }

    public function testNow()
    {
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $this->assertEquals($_SERVER['REQUEST_TIME'], $date->getUnixTimeStamp());
    }

    public function testCreateFromAlaressDateFormat()
    {
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $copiedDate = $this->buildDateObject($date);

        $this->assertEquals($copiedDate->getDatabase(), $date->getDatabase());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testInvalidCreate()
    {
        $this->buildDateObject('2015-03-25');
    }

    public function testCreateFromDateTime()
    {
        $date       = DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME']);
        $copiedDate = $this->buildDateObject($date);
        $this->assertEquals($copiedDate->getDatabase(), $date->format(Alaress_Date_Format::DATABASE_FORMAT));
    }

    public function testCreateFromAlaressDateFormatWithTimezone()
    {
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $date->setTimezone(new DateTimeZone('UTC'));
        $copiedDate = $this->buildDateObject($date, null, new DateTimeZone('Australia/Victoria'));
        $this->assertEquals($copiedDate->getUnixTimeStamp(), $date->getUnixTimeStamp());
        $this->assertEquals('UTC', $date->getTimezone()->getName());
        $this->assertEquals('Australia/Victoria', $copiedDate->getTimezone()->getName());
    }

    public function testCreateFromDateTimeWithTimezone()
    {
        $date       = DateTime::createFromFormat('U', $_SERVER['REQUEST_TIME'], new DateTimeZone('UTC'));
        $copiedDate = $this->buildDateObject($date, null, new DateTimeZone('Australia/Victoria'));
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
        $date  = Alaress_Date_Format::newLocalShort(
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

}
