<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Date\Test;

use Alaress_Date_Format as ADF;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class HumanReadableTest extends TestCase
{
    /**
     * The second arg is returned as a closure so the ADF can be instantiated immediately prior to comparison
     *
     * @return array[]
     */
    public static function dataHumanReadable()
    {
        return [
            [
                'today',
                function () {
                    return ADF::now()->setTime(0, 0, 0);
                },
            ],
            [
                'tomorrow',
                function () {
                    return ADF::now()->addDays(1)->setTime(0, 0, 0);
                },
            ],
            [
                'yesterday at 8:45am',
                function () {
                    return ADF::now()->addDays(-1)->setTime(8, 45, 0);
                },
            ],
            [
                'May 4th 2017 at 2pm',
                function () {
                    return ADF::newDatabaseDate('2017-05-04 14:00:00');
                },
            ],
            [
                'in five days at 9:30am',
                function () {
                    return ADF::now()->addDays(5)->setTime(9, 30, 0);
                },
            ],
            [
                '17th Mar 2018 12:00pm',
                function () {
                    return ADF::newDatabaseDate('2018-03-17 12:00:00');
                },
            ],
            [
                '2 weeks from now',
                function () {
                    return ADF::now()->addDays(14);
                },
            ],
        ];
    }


    /**
     *
     * @param string $naturalLanguage
     * @param callable $callable
     * @throws InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataHumanReadable')]
    public function testHumanReadable($naturalLanguage, $callable)
    {
        $adf = ADF::newNaturalDate($naturalLanguage);

        /** @var ADF $expected */
        $expected = $callable();

        // allow up to 5 seconds of variance between expected and actual
        $this->assertEqualsWithDelta($expected->getUnixTimeStamp(), $adf->getUnixTimeStamp(), 5);
    }
}
