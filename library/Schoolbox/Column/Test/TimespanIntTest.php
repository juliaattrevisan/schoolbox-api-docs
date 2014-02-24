<?php
/**
 * Timespan (stored as integer) column test
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column\Test;
use Schoolbox\Column\TimespanInt;
use DateInterval;

class TimespanIntTest extends TestCase {

    /**
     * @var TimespanInt
     */
    protected $object;

    public function setupObject() {
        $this->object = new TimespanInt();
    }

    public function getTestHydrate() {
        return array(
            array(null,    null),
            array(6023,    new DateInterval('PT1H40M23S')),
            array(0,       new DateInterval('PT0H0M0S')),
            array(86399,   new DateInterval('PT23H59M59S')),
            array(86400,   new DateInterval('PT24H0M0S')),
            array(1231200, new DateInterval('PT342H0M0S')),
            array(4831200, new DateInterval('PT1342H0M0S')),
        );
    }

    /**
     * @dataProvider getTestHydrate
     */
    public function testHydrate($input, $expected_output) {
        $this->assertEquals($expected_output, $this->object->hydrate($input));
    }

    public function getTestDehydrate() {
        return array(
            array(null, null),
            array(false, null),
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
            array(new DateInterval('PT27H15M'), 98100),
            array(new DateInterval('PT27H15S'), 97215),
        );
    }

    /**
     * @dataProvider getTestDehydrate
     */
    public function testDehydrate($input, $expected_output) {
        $this->assertEquals($expected_output, $this->object->dehydrate($input));
    }
}
