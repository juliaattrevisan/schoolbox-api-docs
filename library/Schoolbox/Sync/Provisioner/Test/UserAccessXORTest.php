<?php

/**
 * UserAccessXORTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Sync\Provisioner\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Sync\Provisioner\UserAccessXOR;

class UserAccessXORTest extends TestCase
{
    /**
     * $array1 and $array2 contain the same set of tuples
     *
     * @param array $array1
     * @param array $array2
     * @param int $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_same')]
    public function testSame(array $array1, array $array2, int $expected)
    {

        $diff = (new UserAccessXOR())->generate($array1, $array2);

        $outcome = \count($diff);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testSame
     *
     * @return array
     */
    public static function provider_same(): array
    {
        return [

            /* Both inputs are empty
             */
            'Same 1' => [
                [],
                [],
                0
            ],

            /* Both inputs have the same tuples
             */
            'Same 2' => [
                [
                    305 => 2,
                    295 => 2,
                ],
                [
                    305 => 2,
                    295 => 2,
                ],
                0
            ],

            /* Both inputs have the same tuples
             * but the array keys appear in different order
             */
            'Same 3' => [
                [
                    295 => 2,
                    305 => 2,
                ],
                [
                    305 => 2,
                    295 => 2,
                ],
                0
            ],

            /* Both inputs have the same tuples
             * and the accesslevels are different
             */
            'Same 4' => [
                [
                    295 => 1,
                    305 => 2,
                ],
                [
                    295 => 1,
                    305 => 2,
                ],
                0
            ],

            /* Both inputs have the same tuples
             * but the array keys appear in different order
             * and the accesslevels are different
             */
            'Same 5' => [
                [
                    295 => 1,
                    305 => 2,
                ],
                [
                    305 => 2,
                    295 => 1,
                ],
                0
            ],

            /* Big list
             */
            'Same 6' => [
                [
                    295 => 1,
                    305 => 2,
                    456 => 0,
                    376 => 1,
                    981 => 1,
                    118 => 3,
                    997 => 2,
                ],
                [
                    456 => 0,
                    295 => 1,
                    997 => 2,
                    376 => 1,
                    981 => 1,
                    118 => 3,
                    305 => 2,
                ],
                0
            ],
        ];
    }

    /**
     * $array1 and $array2 contain different set of tuples
     *
     * @param array $array1
     * @param array $array2
     * @param int $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_different')]
    public function testDifferent(array $array1, array $array2, int $expected)
    {

        $diff = (new UserAccessXOR())->generate($array1, $array2);

        $outcome = \count($diff);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testDifferent
     *
     * @return array
     */
    public static function provider_different(): array
    {
        return [

            /* Tuple in first array doesn't appear in second
             * It was removed
             */
            'Different 1' => [
                [
                    305 => 2,
                    295 => 2,
                ],
                [
                    295 => 2,
                ],
                1
            ],

            /* Tuple in first array doesn't appear in second
             * It was removed
             */
            'Different 2' => [
                [
                    305 => 2,
                    295 => 2,
                ],
                [],
                2
            ],

            /* Tuple in second array doesn't appear in first
             * It was added
             */
            'Different 3' => [
                [
                    305 => 2,
                ],
                [
                    295 => 2,
                    305 => 2,
                ],
                1
            ],

            /* Tuple in second array doesn't appear in first
             * It was added to an empty list
             */
            'Different 4' => [
                [],
                [
                    295 => 2,
                    305 => 2,
                ],
                2
            ],

            /* Tuple in second array doesn't appear in first
             * Accesslevels changed
             */
            'Different 5' => [
                [
                    295 => 1,
                    305 => 2,
                ],
                [
                    295 => 3,
                    305 => 3,
                ],
                2
            ],

            /* Big list
             */
            'Different 6' => [
                [
                    295 => 1,
                    376 => 1,
                    118 => 3,
                    997 => 2,
                ],
                [
                    456 => 0,
                    997 => 2,
                    981 => 1,
                    305 => 2,
                    118 => 3,
                ],
                5
            ],
        ];
    }
}
