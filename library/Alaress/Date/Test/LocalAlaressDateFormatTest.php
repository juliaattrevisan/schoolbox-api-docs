<?php

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use PHPUnit\Framework\TestCase;

class LocalAlaressDateFormatTest extends TestCase
{
    use MockDateObject;

    /**
     * Australian Date, 12-hour format with time and morning meridiem
     * Returns correct datetime in mysql format
     *
     * @return void
     */
    public function test12HourWithTimeMorningMeridiem()
    {
        $input = '02/08/2011 8:15 am';
        $expected = '2011-08-02 08:15:00';

        $date = self::buildDateObject($input, 'd/m/Y h:i a');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    /**
     * Australian Date, 12-hour format with time and evening meridiem
     * Returns correct datetime in mysql format
     *
     * @return void
     */
    public function test12HourWithTimeEveningMeridiem()
    {
        $input = '23/08/2011 8:15 pm';
        $expected = '2011-08-23 20:15:00';

        $date = self::buildDateObject($input, 'd/m/Y h:i a');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    /**
     * Australian Date, 12-hour format
     * Returns correct datetime in mysql format
     *
     * @return void
     */
    public function test12Hour()
    {
        $input = '23/08/2011';
        $expected = '2011-08-23 00:00:00';

        $date = self::buildDateObject($input, 'd/m/Y|');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    /**
     * Australian Date, 12-hour format
     * Returns correct datetime in mysql format
     *
     * @return void
     */
    public function testNoon()
    {
        $input = '23/08/2011 12:00 pm';
        $expected = '2011-08-23 12:00:00';

        $date = self::buildDateObject($input, 'd/m/Y h:i a');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    /**
     * Australian Date, 12-hour format
     * Returns correct datetime in mysql format
     *
     * @return void
     */
    public function testMidnight()
    {
        $input = '23/08/2011 12:00 Am';
        $expected = '2011-08-23 00:00:00';

        $date = self::buildDateObject($input, 'd/m/Y H:i a');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    /**
     * Australian Date, 12-hour format
     * Returns correct datetime in mysql format
     *
     * @return void
     */
    public function testDashes()
    {
        $input = '23-08-2011 12:00 Am';
        $expected = '2011-08-23 00:00:00';

        $date = self::buildDateObject($input, 'd-m-Y h:i a');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    public function testUnixTimestamp()
    {
        $input = date('U', strtotime('2011-08-31 19:21:00'));
        $expected = '2011-08-31 19:21:00';

        $date = self::buildDateObject($input, 'U');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    public function testTime()
    {
        $input = date('U', strtotime('2011-08-31 19:21:00'));
        $expected = '7:21pm';

        $date = self::buildDateObject($input, 'U');
        $result = $date->getTimeOnly();

        $this->assertEquals($expected, $result);
    }

    public function testTimeOnly()
    {
        $input = date('U', strtotime('2011-08-31 19:21:00'));
        $expected = '19:21:00';

        $date = self::buildDateObject($input, 'U');
        $result = $date->getDatabaseTime();

        $this->assertEquals($expected, $result);
    }

    public function testBySubmissionComponents()
    {
        $input = '1/9/2011 12:14 pm';
        $expected = '2011-09-01 12:14:00';

        $date = self::buildDateObject($input, 'd/m/Y h:i a');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }

    public function testDbFormat()
    {
        $input = '2011-07-01 20:51:00';
        $expected = '2011-07-01 20:51:00';

        $date = self::buildDateObject($input, 'Y-m-d H:i:s');
        $result = $date->getDatabase();

        $this->assertEquals($expected, $result);
    }
}
