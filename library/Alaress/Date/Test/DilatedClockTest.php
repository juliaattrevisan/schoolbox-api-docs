<?php
/**
 * Test for verifying that \Alaress\Date\Dilated works as expected.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2016 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Alaress\Date\Test;

use Alaress\Date\DilatedClock;
use PHPUnit\Framework\TestCase;

class DilatedClockTest extends TestCase
{
    /**
     * Verifies that a clock for right now keeps the correct time.
     */
    public function testClockNow()
    {
        $clock = new DilatedClock();
        sleep(1);
        $this->assertEquals(time(), $clock->time());
    }

    /**
     * Verifies that a clock for past keeps the correct time.
     */
    public function testSlowClockInPast()
    {
        $now = time();
        $clock = new DilatedClock(3, '@' . ($now - 1));
        sleep(3);
        $this->assertEquals($now, $clock->time());
    }

    /**
     * Verifies that a clock for future keeps the correct time.
     */
    public function testFastClockInPast()
    {
        $now = time();
        $clock = new DilatedClock(1 / 10, '@' . ($now - 3));
        sleep(1);
        $this->assertEquals($now + 7, $clock->time());
    }

    /**
     * Verifies that a clock for past keeps the correct time.
     */
    public function testSlowClockInFuture()
    {
        $now = time();
        $clock = new DilatedClock(3, '@' . ($now + 1));
        sleep(3);
        $this->assertEquals($now + 2, $clock->time());
    }

    /**
     * Verifies that a clock for future keeps the correct time.
     */
    public function testFastClockInFuture()
    {
        $now = time();
        $clock = new DilatedClock(1 / 10, '@' . ($now + 1));
        sleep(1);
        $this->assertEquals($now + 11, $clock->time());
    }
}
