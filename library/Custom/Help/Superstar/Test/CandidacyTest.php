<?php

/**
 * CandidacyTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Custom\Help\Superstar\Test;

use Custom\Help\Superstar\Candidacy;
use PHPUnit\Framework\TestCase;

class CandidacyTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerOutcomes')]
    public function testOutcomes($input, $expected)
    {
        /* Substitute this with a spec pattern or other conditional
         */
        $outcome = (new Candidacy())->decide($input);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testOutcomes
     *
     * @return array
     */
    public static function providerOutcomes(): array
    {
        return [
            /* No marks provided; nothing to award
             */
            'Nothing 1' => [
                [],
                null
            ],

            /* Didn't reach minimum threshold for celebrity status;
             * nothing to award
             */
            'Nothing 2' => [
                [0.00],
                null
            ],

            'Nothing 3' => [
                [0.00, 1.29, 68.99, 99.12],
                null
            ],

            'Nothing 4' => [
                [100.00, 99.12, 99.34, 99.45, 98.99],
                null
            ],

            'Nothing 5' => [
                [100.00, 99.12, 99.34, 99.45, 98.99],
                null
            ],

            'Nothing 6' => [
                [100.00, 99.12, 0.00, 99.34, null, null, 0.00, 99.45, 98.99],
                null
            ],

            /* Star
             */
            'Star 1' => [
                [99.00, 100.00, 100.00, 100.00],
                Candidacy::STAR
            ],

            'Star 2' => [
                [99.00, 99.12, 99.00, 99.76, 100.00, 100.00, 100.00],
                Candidacy::STAR
            ],

            /* Superstar
             */
            'Superstar 1' => [
                [100.00],
                Candidacy::SUPERSTAR
            ],

            'Superstar 2' => [
                [100.00, 100.00, 100.00, 100.00, 100.00, 100.00],
                Candidacy::SUPERSTAR
            ],

            /* Star, but with a new project they haven't done yet
             * They get nothing
             */
            'Incomplete Star 1' => [
                [99.00, 100.00, 100.00, 100.00, null],
                null
            ],

            'Incomplete Star 2' => [
                [99.00, 99.12, 99.00, 99.76, 100.00, 100.00, 100.00, null],
                null
            ],

            'Incomplete Star 3' => [
                [99.00, null, 99.12, null, 99.00, null, 99.76, null, 100.00, null, 100.00, null, 100.00, null],
                null
            ],

            /* Superstar, but with a new project they haven't done yet
             * They get nothing
             */
            'Incomplete Superstar 1' => [
                [100.00, null],
                null
            ],

            'Incomplete Superstar 2' => [
                [100.00, 100.00, 100.00, 100.00, 100.00, 100.00, null],
                null
            ],

            'Incomplete Superstar 3' => [
                [100.00, null, 100.00, null, 100.00, null, 100.00, null, 100.00, null, 100.00, null],
                null
            ],
        ];
    }
}
