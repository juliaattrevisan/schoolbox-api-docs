<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Jitter\Test;

use Alaress\Jitter\Jitter;
use Alaress\Random\PredeterminedGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Class JitterTest
 */
class JitterTest extends TestCase
{
    /**
     * Test jitter
     */
    public function testJitter()
    {
        $jitter = new Jitter(new PredeterminedGenerator([0.3, 0.1, 0.5, 0.7, 0.9]), 5, 100);

        // Attempt -1: always 0 too, I guess
        $this->assertEquals(0, $jitter->calculateJitter(-1));

        // Attempt 0: always 0
        $this->assertEquals(0, $jitter->calculateJitter(0));

        // Jitter algo:
        // timeout * min(maxAttempts, pow(2, attempts-1)) * random()

        // Attempt 1: random .3
        $this->assertEquals(30, $jitter->calculateJitter(1));

        // Attempt 2: random .1
        $this->assertEquals(20, $jitter->calculateJitter(2));

        // Attempt 3: random .5
        $this->assertEquals(200, $jitter->calculateJitter(3));

        // Attempt 4: random .7
        // 2 ^ (4-1) is greater than max attempts, so multiply by that instead
        $this->assertEquals(350, $jitter->calculateJitter(4));

        // Attempt 5: too many
        $this->assertTrue($jitter->tooManyAttempts(5));
    }

    /**
     * Test jitter with no attempts (maxAttempts = 1)
     */
    public function testJitterWithNoAttempts()
    {
        $jitter = new Jitter(new PredeterminedGenerator([0.3, 0.1]), 1, 100);

        // Attempt -1: always 0 too, I guess
        $this->assertEquals(0, $jitter->calculateJitter(-1));

        // Attempt 0: always 0
        $this->assertEquals(0, $jitter->calculateJitter(0));

        // Attempt 1: too many
        $this->assertTrue($jitter->tooManyAttempts(1));
    }

    /**
     * Test jitter object when max attempts is exceeded
     *
     * @expectedException \Alaress\Jitter\MaxAttemptsExceededException
     */
    public function testJitterMaxAttempts()
    {
        $jitter = new Jitter(new PredeterminedGenerator([0.3, 0.1, 0.5, 0.7, 0.9]), 5, 100);

        $jitter->calculateJitter(5);
    }
}
