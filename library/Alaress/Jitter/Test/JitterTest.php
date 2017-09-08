<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Jitter\Test;

use Alaress\Jitter\Jitter;
use Alaress\Random\PredeterminedGenerator;
use PHPUnit_Framework_TestCase;

/**
 * Class JitterTest
 */
class JitterTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test jitter
     */
    public function testJitter()
    {
        $jitter = new Jitter(new PredeterminedGenerator([0.3, 0.1, 0.5, 0.7, 0.9]), 5, 100);

        // Retry -1: always 0 too, I guess
        $this->assertEquals(0, $jitter->calculateJitter(-1));

        // Retry 0: always 0
        $this->assertEquals(0, $jitter->calculateJitter(0));

        // Jitter algo:
        // timeout * min(maxRetries, pow(2, retries-1)) * random()

        // Retry 1: random .3
        $this->assertEquals(30, $jitter->calculateJitter(1));

        // Retry 2: random .1
        $this->assertEquals(20, $jitter->calculateJitter(2));

        // Retry 3: random .5
        $this->assertEquals(200, $jitter->calculateJitter(3));

        // Retry 4: random .7
        // 2 ^ (4-1) is greater than max retries, so multiply by that instead
        $this->assertEquals(350, $jitter->calculateJitter(4));

        // Retry 5: too many
        $this->assertTrue($jitter->tooManyRetries(5));
    }

    /**
     * Test jitter with no retries
     */
    public function testJitterWithNoRetries()
    {
        $jitter = new Jitter(new PredeterminedGenerator([0.3, 0.1]), 0, 100);

        // Retry -1: always 0 too, I guess
        $this->assertEquals(0, $jitter->calculateJitter(-1));

        // Retry 0: always 0
        $this->assertEquals(0, $jitter->calculateJitter(0));

        // Retry 1: too many
        $this->assertTrue($jitter->tooManyRetries(1));
    }

    /**
     * Test jitter object when max retries is exceeded
     *
     * @expectedException \Alaress\Jitter\MaxRetriesExceededException
     */
    public function testJitterMaxRetries()
    {
        $jitter = new Jitter(new PredeterminedGenerator([0.3, 0.1, 0.5, 0.7, 0.9]), 5, 100);

        $jitter->calculateJitter(6);
    }
}
