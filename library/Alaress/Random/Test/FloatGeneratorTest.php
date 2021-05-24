<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Random\Test;

use Alaress\Random\FloatGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Test the float generator
 */
class FloatGeneratorTest extends TestCase
{
    /**
     * Test for the float generator.
     */
    public function testGeneration()
    {
        // Can't test much here; just verify all results are between 0 and 1
        // Even seeding RNG does NOT guarantee consistency between versions,
        // as the seeding algorithm can change (and has between PHP5 and 7)
        $rng = new FloatGenerator(1234567);
        foreach (range(1, 1000) as $i) {
            $random = $rng->generate();
            $this->assertIsFloat($random);
            $this->assertGreaterThanOrEqual(0, $random);
            $this->assertLessThanOrEqual(1, $random);
        }
    }
}
