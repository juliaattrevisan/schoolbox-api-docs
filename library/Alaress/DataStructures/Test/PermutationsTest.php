<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Alaress\DataStructures\Test;

use Alaress\DataStructures\Permutations;
use PHPUnit\Framework;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Alaress\DataStructures\Permutations
 */
class PermutationsTest extends TestCase
{

    /**
     * Test we generate the expected number of permutations
     *
     * @throws Framework\Exception
     */
    public function testGenerate(): void
    {
        $generator    = new Permutations();
        $permutations = $generator->generate([1, 2, 3]);

        /* [1,2,3]
         * [1,3,2]
         * [3,1,2]
         * [3,2,1]
         * [2,1,3]
         * [2,3,1]
         */
        $this->assertCount(6, $permutations);
    }


    /**
     * Test we generate the expected number of distinct permutations
     *
     * @throws Framework\Exception
     */
    public function testGenerateDistinct(): void
    {
        $generator    = new Permutations();
        $permutations = $generator->generateDistinct([1, 2, 1]);

        /* [1,2,1]
         * [1,1,2]
         * [2,1,1]
         */
        $this->assertCount(3, $permutations);
    }
}
