<?php

/**
 * ValidationTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Test;

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validExample')]
    public function testValidExample($input, $expected)
    {
        /* Substitute this with a spec pattern or other conditional
         */
        $outcome = false;

        if (is_numeric($input)) {
            $outcome = true;
        }

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function provider_validExample(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'Valid Example 1' => [
                0,    // goes into testValidExample() as $input
                true  // goes into testValidExample() as $expected
            ],

            'Valid Example 2' => [
                1,
                true
            ],

            'Valid Example 3' => [
                123456789,
                true
            ],

            'Valid Example 4' => [
                '1e4',
                true
            ],

            /* Add other cases that you expect to be invalid
             */
            'Invalid Example 1' => [
                false,
                false
            ],

            'Invalid Example 2' => [
                'a',
                false
            ],

            'Invalid Example 3' => [
                null,
                false
            ],

            'Invalid Example 4' => [
                '',
                false
            ],
        ];
    }


    /**
     * You should also test for error states that your code may raise.
     * Use the `$this->expectException` to verify which exception class
     * you expect your code to raise.
     *
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestErrorRaisedData')]
    public function testErrorRaised($input): void
    {
        $this->expectException(\TypeError::class);
        // Substitute this with the method call that is supposed to fail.
        $test = function (int $b) {
            return $b + 1;
        };
        $test($input);
    }


    /**
     * Data provider for `testErrorRaised`.
     * To allow the return everything is wrapped in an array.
     *
     * @return array
     */
    public static function getTestErrorRaisedData(): array
    {
        return [
            [1.5],
            [null],
            ['a string'],
            ['1.5'],
            [new \stdClass()],
            [[]],
            [STDOUT]
        ];
    }
}
