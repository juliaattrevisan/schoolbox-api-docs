<?php
/**
 * Timespan column test
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Column\Test;
use Schoolbox\Column\Timespan;
use DateInterval;

class TimespanTest extends TestCase {

    /**
     * @var Timespan
     */
    protected $object;

    public function setupObject() {
        $this->object = new Timespan();
    }

    public function getTestHydrate() {
        return array(
            array(null,        null),
            array('01:40:23',  new DateInterval('PT1H40M23S')),
            array('00:00:00',  new DateInterval('PT0H0M0S')),
            array('23:59:59',  new DateInterval('PT23H59M59S')),
            array('24:00:00',  new DateInterval('PT24H0M0S')),
            array('342:00:00', new DateInterval('PT342H0M0S')),
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
            array('00:00:00', '00:00:00'),
            array('1:59:59', '01:59:59'),
            array('23:59:59', '23:59:59'),
            array('24:00:00', '24:00:00'),
            array('342:00:00', '342:00:00'),
            array('1:56', '01:56:00'),
            array('01:56', '01:56:00'),
            array('101:56', '101:56:00'),
            // DateInterval objects:
            // note that days, weeks, years, etc. will be ignored because they
            // cannot reliably be cast to hours
            array(new DateInterval('PT27H15M'), '27:15:00'),
            array(new DateInterval('PT27H15S'), '27:00:15'),
        );
    }

    /**
     * @dataProvider getTestDehydrate
     */
    public function testDehydrate($input, $expected_output) {
        $this->assertEquals($expected_output, $this->object->dehydrate($input));
    }
}
