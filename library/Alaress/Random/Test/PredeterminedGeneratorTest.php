<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Random\Test;

use Alaress\Random\PredeterminedGenerator;
use LengthException;
use PHPUnit\Framework\TestCase;

/**
 * Test the float generator
 */
class PredeterminedGeneratorTest extends TestCase
{
    /**
     * Test that I can't create a generator with no predetermined values.
     *
     *
     */
    public function testGenerationWithEmptyGenerator()
    {
        $this->expectException(LengthException::class);
        new PredeterminedGenerator([]);
    }

    /**
     * Test for the predetermined generator.
     */
    public function testGeneration()
    {
        $rng = new PredeterminedGenerator([1.3, 6.3, 5.1, 'goop']);

        // Cycle 1
        $this->assertSame(1.3, $rng->generate());
        $this->assertSame(6.3, $rng->generate());
        $this->assertSame(5.1, $rng->generate());
        $this->assertSame('goop', $rng->generate());

        // Cycle 2
        $this->assertSame(1.3, $rng->generate());
        $this->assertSame(6.3, $rng->generate());
        $this->assertSame(5.1, $rng->generate());
        $this->assertSame('goop', $rng->generate());

        // Cycle 3
        $this->assertSame(1.3, $rng->generate());
        $this->assertSame(6.3, $rng->generate());
        $this->assertSame(5.1, $rng->generate());
        $this->assertSame('goop', $rng->generate());
    }
}
