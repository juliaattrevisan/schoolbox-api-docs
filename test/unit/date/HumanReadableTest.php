<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Alaress_Date_Format as ADF;

class HumanReadableTest extends PHPUnit_Framework_TestCase
{

    /**
     * @return array[]
     */
    public function dataHumanReadable()
    {
        return [
            [ 'today', ADF::now()->setTime(0, 0, 0) ],
            [ 'tomorrow', ADF::now()->addDays(1)->setTime(0, 0, 0) ],
            [ 'yesterday at 8:45am', ADF::now()->addDays(-1)->setTime(8, 45, 0) ],
            [ 'May 4th 2017 at 2pm', ADF::newDatabaseDate('2017-05-04 14:00:00') ],
            [ 'in 5 days at 9:30am', ADF::now()->addDays(5)->setTime(9, 30, 0) ],
            [ '17th Mar 2018 12:00pm', ADF::newDatabaseDate('2018-03-17 12:00:00') ],
            [ '2 weeks from now', ADF::now()->addDays(14) ],
        ];
    }


    /**
     * @dataProvider dataHumanReadable
     *
     * @param string $naturalLanguage
     * @param ADF   $expected
     * @throws InvalidArgumentException
     */
    public function testHumanReadable($naturalLanguage, $expected)
    {
        $adf = ADF::newNaturalDate($naturalLanguage);

        // zero the seconds to account for CPU lag
        $this->assertEquals($expected->setSecond(0)->getDatabase(), $adf->setSecond(0)->getDatabase());
    }

}
