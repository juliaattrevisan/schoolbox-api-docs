<?php
/**
 * Test for verifying that setting 12 hour dates works as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

class MeridiemTest extends PHPUnit_Framework_TestCase
{
    use Alaress\Test\Date\MockDateObject;

    /**
     * Data sets for testSet12Hour
     */
    public function getSet12HourData()
    {
        return [
            [12, 'am', 0],
            [1, 'am', 1],
            [2, 'am', 2],
            [3, 'am', 3],
            [4, 'am', 4],
            [5, 'am', 5],
            [6, 'am', 6],
            [7, 'am', 7],
            [8, 'am', 8],
            [9, 'am', 9],
            [10, 'am', 10],
            [11, 'am', 11],
            [12, 'pm', 12],
            [1, 'pm', 13],
            [2, 'pm', 14],
            [3, 'pm', 15],
            [4, 'pm', 16],
            [5, 'pm', 17],
            [6, 'pm', 18],
            [7, 'pm', 19],
            [8, 'pm', 20],
            [9, 'pm', 21],
            [10, 'pm', 22],
            [11, 'pm', 23],
        ];
    }

    /**
     * @dataProvider getSet12HourData
     *
     * @param int    $hour
     * @param string $meridiem
     * @param int    $expectedHour
     */
    public function testSet12Hour($hour, $meridiem, $expectedHour)
    {
        $date = $this->buildDateObject();
        $date->set12Hour($hour, $meridiem);
        $this->assertEquals($expectedHour, $date->get24Hour());
    }

    /**
     * Data sets for testSetInvalid12Hour
     */
    public function getSetInvalid12HourData()
    {
        return [
            [0, 'am'],
            [13, 'am'],
            [0, 'pm'],
            [13, 'pm'],
            [1, 'bm',],
            [2, 'cm',],
            [null, 'am',],
            [null, null,],
            [3, null,],
        ];
    }

    /**
     * @dataProvider getSetInvalid12HourData
     *
     * @expectedException PHPUnit_Framework_Error_Warning
     *
     * @param int    $hour
     * @param string $meridiem
     */
    public function testSetInvalid12Hour($hour, $meridiem)
    {
        $date = $this->buildDateObject();
        $date->set12Hour($hour, $meridiem);
    }

    /**
     * Data sets for testSet12HourTime
     */
    public function getSet12HourTimeData()
    {
        return [
            [12, 0, 0, 'am', '00:00:00'],
            [11, 59, 59, 'am', '11:59:59'],
            [12, 0, 0, 'pm', '12:00:00'],
            [11, 59, 59, 'pm', '23:59:59'],
        ];
    }

    /**
     * @dataProvider getSet12HourTimeData
     *
     * @param int    $hour
     * @param int    $minute
     * @param int    $second
     * @param string $meridiem
     * @param string $expectedTime in H:i:s format
     */
    public function testSet12HourTime($hour, $minute, $second, $meridiem, $expectedTime)
    {
        $date = $this->buildDateObject();
        $date->set12HourTime($hour, $minute, $second, $meridiem);
        $this->assertEquals($expectedTime, $date->getUsingFormat('H:i:s'));
    }

    /**
     * Data sets for testSetInvalid12HourTime
     */
    public function getSetInvalid12HourTimeData()
    {
        return [
            [0, 0, 0, 'am'],
            [13, 0, 0, 'am'],
            [0, 0, 0, 'pm'],
            [13, 0, 0, 'pm'],
            [1, 0, 0, 'bm',],
            [2, 0, 0, 'cm',],
            [null, 0, 0, 'am',],
            [null, 0, 0, null,],
            [3, 0, 0, null,],
        ];
    }

    /**
     * @dataProvider getSetInvalid12HourTimeData
     *
     * @expectedException PHPUnit_Framework_Error_Warning
     *
     * @param int    $hour
     * @param int    $minute
     * @param int    $second
     * @param string $meridiem
     */
    public function testSetInvalid12HourTime($hour, $minute, $second, $meridiem)
    {
        $date = $this->buildDateObject();
        $date->set12HourTime($hour, $minute, $second, $meridiem);
    }
}
