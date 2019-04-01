<?php

/**
 * ValidationTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Example\Test;

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    /**
     * @dataProvider provider_validExample
     * @param mixed $input
     * @param int   $expected
     */
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
    public function provider_validExample() : array {
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
}
