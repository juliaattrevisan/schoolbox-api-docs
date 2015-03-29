<?php
/**
 * Test for verifying that basic get parts of date methods work as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

class BasicGetPartTest extends PHPUnit_Framework_TestCase
{
    use Alaress\Test\Date\MockDateObject;

    protected function getTestDateObject(DateTimeZone $timezone = null)
    {
        return $this->buildDateObject('2015-03-05 08:01:03', Alaress_Date_Format::DATABASE_FORMAT, $timezone);
    }

    public function testGetSecond()
    {
        $date = $this->getTestDateObject();
        $this->assertSame($date->getSecond(), '3');
        $this->assertSame($date->getSecond(true), '03');
    }

    public function testGetMinute()
    {
        $date = $this->getTestDateObject();
        $this->assertSame($date->getMinute(), '1');
        $this->assertSame($date->getMinute(true), '01');
    }

    public function testGetHour()
    {
        $date = $this->getTestDateObject();
        $this->assertSame($date->getHour(), '8');
        $this->assertSame($date->getHour(true), '08');
    }

    public function testGetDay()
    {
        $date = $this->getTestDateObject();
        $this->assertSame($date->getDay(), '5');
        $this->assertSame($date->getDay(true), '05');
    }

    public function testGetMonth()
    {
        $date = $this->getTestDateObject();
        $this->assertSame($date->getMonth(), '3');
        $this->assertSame($date->getMonth(true), '03');
    }

    public function testGetYear()
    {
        $date = $this->getTestDateObject();
        $this->assertSame($date->getYear(), '2015');
    }
}
