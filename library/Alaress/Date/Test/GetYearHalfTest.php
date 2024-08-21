<?php

/**
 * Test for verifying that getting the year half works.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress\Test\Date\MockDateObject;
use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;

class GetYearHalfTest extends TestCase
{
    use MockDateObject;

    /**
     * Data sets for testGetYearHalf
     */
    public function getTestYearHalfData()
    {
        return [
            [self::buildDateObject('2015-01-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT), 1],
            [self::buildDateObject('2015-06-30 23:59:59', Alaress_Date_Format::DATABASE_FORMAT), 1],
            [self::buildDateObject('2015-07-01 00:00:00', Alaress_Date_Format::DATABASE_FORMAT), 2],
            [self::buildDateObject('2015-12-31 23:59:59', Alaress_Date_Format::DATABASE_FORMAT), 2],
        ];
    }

    /**
     *
     * @param Alaress_Date_Format $date
     * @param int                 $expectedHalf
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestYearHalfData')]
    public function testGetYearHalf($date, $expectedHalf)
    {
        $this->assertEquals($expectedHalf, $date->getYearHalf());
    }
}
