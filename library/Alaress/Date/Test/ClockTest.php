<?php
/**
 * Test for verifying that \Alaress\Date\Clock works as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Alaress\Date\Test;

use Alaress\Date\Clock;
use PHPUnit\Framework\TestCase;

class ClockTest extends TestCase
{
    /**
     * Verifies that a clock for right now keeps the correct time.
     */
    public function testClockNow()
    {
        $clock = new Clock();
        sleep(1);
        $this->assertEquals(time(), $clock->time());
    }

    /**
     * Verifies that a clock for past keeps the correct time.
     */
    public function testClockInPast()
    {
        $clock = new Clock('-1 year');
        sleep(1);
        $this->assertEquals(strtotime('-1 year'), $clock->time());
    }

    /**
     * Verifies that a clock for future keeps the correct time.
     */
    public function testClockInFuture()
    {
        $clock = new Clock('+1 year');
        sleep(1);
        $this->assertEquals(strtotime('+1 year'), $clock->time());
    }
}
