<?php

/**
 * Test for verifying that setting 12 hour dates works as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MeridiemTest extends TestCase
{
    use MockDateObject;

    /**
     * Data sets for testSet12Hour
     */
    public static function getSet12HourData()
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
     *
     * @param int    $hour
     * @param string $meridiem
     * @param int    $expectedHour
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSet12HourData')]
    public function testSet12Hour($hour, $meridiem, $expectedHour)
    {
        $date = $this->buildDateObject();
        $date->set12Hour($hour, $meridiem);
        $this->assertEquals($expectedHour, $date->get24Hour());
    }

    /**
     * Data sets for testSetInvalid12Hour
     */
    public static function getSetInvalid12HourData()
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
     *
     * @param int    $hour
     * @param string $meridiem
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSetInvalid12HourData')]
    public function testSetInvalid12Hour($hour, $meridiem)
    {
        $this->expectException(InvalidArgumentException::class);
        $date = $this->buildDateObject();
        $date->set12Hour($hour, $meridiem);
    }

    /**
     * Data sets for testGet12HourTime
     */
    public function getGet12HourTimeData()
    {
        return [
            // No leading zeros (default)
            [$this->buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT), '12', false],
            [$this->buildDateObject('2015-03-25 00:00:01', Alaress_Date_Format::DATABASE_FORMAT), '12', false],
            [$this->buildDateObject('2015-03-25 01:00:00', Alaress_Date_Format::DATABASE_FORMAT), '1', false],
            [$this->buildDateObject('2015-03-25 11:59:59', Alaress_Date_Format::DATABASE_FORMAT), '11', false],
            [$this->buildDateObject('2015-03-25 12:00:00', Alaress_Date_Format::DATABASE_FORMAT), '12', false],
            [$this->buildDateObject('2015-03-25 12:00:01', Alaress_Date_Format::DATABASE_FORMAT), '12', false],
            [$this->buildDateObject('2015-03-25 13:00:00', Alaress_Date_Format::DATABASE_FORMAT), '1', false],
            [$this->buildDateObject('2015-03-25 23:59:59', Alaress_Date_Format::DATABASE_FORMAT), '11', false],
            // Leading zeros
            [$this->buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT), '12', true],
            [$this->buildDateObject('2015-03-25 00:00:01', Alaress_Date_Format::DATABASE_FORMAT), '12', true],
            [$this->buildDateObject('2015-03-25 01:00:00', Alaress_Date_Format::DATABASE_FORMAT), '01', true],
            [$this->buildDateObject('2015-03-25 11:59:59', Alaress_Date_Format::DATABASE_FORMAT), '11', true],
            [$this->buildDateObject('2015-03-25 12:00:00', Alaress_Date_Format::DATABASE_FORMAT), '12', true],
            [$this->buildDateObject('2015-03-25 12:00:01', Alaress_Date_Format::DATABASE_FORMAT), '12', true],
            [$this->buildDateObject('2015-03-25 13:00:00', Alaress_Date_Format::DATABASE_FORMAT), '01', true],
            [$this->buildDateObject('2015-03-25 23:59:59', Alaress_Date_Format::DATABASE_FORMAT), '11', true],
        ];
    }

    /**
     *
     *
     * @param Alaress_Date_Format $date
     * @param int                 $expected
     * @param bool                $leadingZero
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getGet12HourTimeData')]
    public function testGet12HourTime($date, $expected, $leadingZero)
    {
        $this->assertSame($expected, $date->get12Hour($leadingZero));
    }

    /**
     * Data sets for testSet12HourTime
     */
    public static function getSet12HourTimeData()
    {
        return [
            [12, 0, 0, 'am', '00:00:00'],
            [11, 59, 59, 'am', '11:59:59'],
            [12, 0, 0, 'pm', '12:00:00'],
            [11, 59, 59, 'pm', '23:59:59'],
        ];
    }

    /**
     *
     * @param int    $hour
     * @param int    $minute
     * @param int    $second
     * @param string $meridiem
     * @param string $expectedTime in H:i:s format
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSet12HourTimeData')]
    public function testSet12HourTime($hour, $minute, $second, $meridiem, $expectedTime)
    {
        $date = $this->buildDateObject();
        $date->set12HourTime($hour, $minute, $second, $meridiem);
        $this->assertEquals($expectedTime, $date->getUsingFormat('H:i:s'));
    }

    /**
     * Data sets for testSetInvalid12HourTime
     */
    public static function getSetInvalid12HourTimeData()
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
     *
     * @param int    $hour
     * @param int    $minute
     * @param int    $second
     * @param string $meridiem
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getSetInvalid12HourTimeData')]
    public function testSetInvalid12HourTime($hour, $minute, $second, $meridiem)
    {
        $this->expectException(InvalidArgumentException::class);
        $date = $this->buildDateObject();
        $date->set12HourTime($hour, $minute, $second, $meridiem);
    }

    /**
     * Data sets for testIsAm and testIsPm
     */
    public function getIsAmData()
    {
        return [
            [$this->buildDateObject('2015-03-25 00:00:00', Alaress_Date_Format::DATABASE_FORMAT), true],
            [$this->buildDateObject('2015-03-25 00:00:01', Alaress_Date_Format::DATABASE_FORMAT), true],
            [$this->buildDateObject('2015-03-25 11:59:59', Alaress_Date_Format::DATABASE_FORMAT), true],
            [$this->buildDateObject('2015-03-25 12:00:00', Alaress_Date_Format::DATABASE_FORMAT), false],
            [$this->buildDateObject('2015-03-25 12:00:01', Alaress_Date_Format::DATABASE_FORMAT), false],
            [$this->buildDateObject('2015-03-25 23:59:59', Alaress_Date_Format::DATABASE_FORMAT), false],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isAm
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getIsAmData')]
    public function testIsAm($date, $isAm)
    {
        $this->assertEquals($isAm, $date->isAM());
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isAm
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getIsAmData')]
    public function testIsPm($date, $isAm)
    {
        $this->assertNotEquals($isAm, $date->isPM());
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isAm
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getIsAmData')]
    public function testIsMeridiem($date, $isAm)
    {
        $meridiem = $isAm ? Alaress_Date_Format::AM : Alaress_Date_Format::PM;
        $this->assertTrue($date->isMeridiem($meridiem));
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param bool                $isAm
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getIsAmData')]
    public function testGetMeridiem($date, $isAm)
    {
        $this->assertEquals(
            $isAm ? 'AM' : 'PM',
            $date->getMeridiem()
        );
    }

    public function testInvalidIsMeridiem()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->assertTrue($this->buildDateObject()->isMeridiem('cm'));
    }
}
