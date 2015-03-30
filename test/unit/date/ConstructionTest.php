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

    public function testNow()
    {
        $currentDatetime = $_SERVER['REQUEST_TIME'];
        if (!$currentDatetime) {
            $this->markTestSkipped('No $_SERVER[REQUEST_TIME], cannot accurately test Alaress_Date_Format::now()');
        }
        $date = Alaress_Date_Format::now();
        $date->setLocale(new Alaress_Date_Locale_Static());
        $this->assertEquals($currentDatetime, $date->getUnixTimeStamp());
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
}
