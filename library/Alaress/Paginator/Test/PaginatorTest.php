<?php
/**
 * Unit tests for Alaress\Paginator\Paginator class.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Paginator\Test;
use Alaress\Paginator\Paginator;
use PHPUnit_Framework_TestCase;

class PaginatorTest extends PHPUnit_Framework_TestCase {


    public function testZeroPagesize() {
        $this->setExpectedException('OutOfRangeException');
        new Paginator(0, 0);
    }

    public function testNegativePagesize() {
        $this->setExpectedException('OutOfRangeException');
        new Paginator(-1, 0);
    }


    /**
     * @return array
     */
    public function getTestOffestData() {
        return array(
            array(new Paginator(1, 0), 0),
            array(new Paginator(1, 10), ),
        );
    }

    /**
     * @dataProvider getTestOffestData
     */
    public function testOffset(Paginator $paginator, $page, $expected) {
    }

}
