<?php

/**
 * Interval (stored as integer) column test
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Column\Test;

use Schoolbox\Column\Interval;
use DateInterval;
use Alaress\Date\Interval as IntervalObject;

class IntervalTest extends TestCase
{

    /**
     * @var Interval
     */
    protected $object;

    /**
     * @inheritdoc
     */
    public function setupObject()
    {
        $this->object = new Interval();
    }

    /**
     * @inheritdoc
     */
    public static function getTestHydrate()
    {
        $invertedInterval1 = new DateInterval('PT23H59M59S');
        $invertedInterval1->invert = 1;
        $invertedInterval2 = new DateInterval('PT24H0M0S');
        $invertedInterval2->invert = 1;
        $invertedInterval3 = new DateInterval('PT342H0M0S');
        $invertedInterval3->invert = 1;
        return array(
            array(null,     null),
            array(6023,     IntervalObject::createFromDateInterval(new DateInterval('PT1H40M23S'))),
            array(0,        IntervalObject::createFromDateInterval(new DateInterval('PT0H0M0S'))),
            array(86399,    IntervalObject::createFromDateInterval(new DateInterval('PT23H59M59S'))),
            array(86400,    IntervalObject::createFromDateInterval(new DateInterval('PT24H0M0S'))),
            array(1231200,  IntervalObject::createFromDateInterval(new DateInterval('PT342H0M0S'))),
            array(4831200,  IntervalObject::createFromDateInterval(new DateInterval('PT1342H0M0S'))),
            array(-86399,   IntervalObject::createFromDateInterval($invertedInterval1)),
            array(-86400,   IntervalObject::createFromDateInterval($invertedInterval2)),
            array(-1231200, IntervalObject::createFromDateInterval($invertedInterval3)),
            array(-1,       new IntervalObject(-1)),
            array(0,        new IntervalObject(0)),
            array(1,        new IntervalObject(1)),
        );
    }

    /**
     * @inheritdoc
     */
    public static function getTestDehydrate()
    {
        $invertedInterval = new DateInterval('PT27H15M');
        $invertedInterval->invert = 1;
        return array(
            array(null, null),
            array(false, null),
            // ints and int-alikes: passed thru unmolested
            array(0, 0),
            array(7199, 7199),
            array(86399, 86399),
            array(86400, 86400),
            array(1231200, 1231200),
            array(-1231200, -1231200),
            // string formats: H:M:S, H:M
            array('00:00:00', 0),
            array('1:59:59', 7199),
            array('23:59:59', 86399),
            array('24:00:00', 86400),
            array('342:00:00', 1231200),
            array('1:56', 6960),
            array('01:56', 6960),
            array('101:56', 366960),
            // DateInterval objects:
            // note that days, weeks, years, etc. will be ignored because they
            // cannot reliably be cast to hours
            array(new DateInterval('PT75M'), 4500),
            array(new DateInterval('PT75S'), 75),
            array(new DateInterval('PT27H15M'), 98100),
            array(new DateInterval('PT27H15S'), 97215),
            // Inverted intervals
            array('-101:56', -366960),
            // IntervalObject
            array(new IntervalObject(1), 1),
            array(new IntervalObject(0), 0),
            array(new IntervalObject(-1), -1),
            array($invertedInterval, -98100),
        );
    }

    /**
     * @param null|string $input
     * @param null|IntervalObject $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestHydrate')]
    public function testHydrate($input = null, $expected_output = null)
    {
        $this->assertEquals($expected_output, $this->object->hydrate($input));
    }

    /**
     * @param IntervalObject|DateInterval|int|string $input
     * @param null|int $expected_output
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestDehydrate')]
    public function testDehydrate($input = null, $expected_output = null)
    {
        $this->assertEquals($expected_output, $this->object->dehydrate($input));
    }
}
