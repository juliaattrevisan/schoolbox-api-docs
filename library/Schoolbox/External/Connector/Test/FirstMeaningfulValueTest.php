<?php

/**
 * FirstMeaningfulValueTest
 *
 * See SD-10 for usages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\External\Connector\Test;

use PHPUnit\Framework\TestCase;

/**
 * Class FirstMeaningfulValueTest
 *
 * From an array of values, find the first meaningful value
 * The value must not be any of the following possibilities:
 *
 * null
 * an empty string
 * consist only of spaces
 *
 * If the array of values contains no meaningful values,
 * default to an empty string
 *
 * @package Schoolbox\External\Connector\Test
 */
class FirstMeaningfulValueTest extends TestCase
{

    /**
     * @dataProvider providerValidMeaningfulValue
     * @param array  $input
     * @param string $expected
     */
    public function testValidMeaningfulValue(array $input, string $expected)
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
    public function providerValidMeaningfulValue(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'Valid Example 1' => [
                [],
                ''
            ],

            'Valid Example 2' => [
                [ null, null, '', '     ' ],
                ''
            ],

            'Valid Example 3' => [
                [ null, null, '', '     ', 0 ],
                '0'
            ],

            'Valid Example 4' => [
                [ null, null, '', '     ', 0, 'cat', 'horse', '0'],
                '0'
            ],

            'Valid Example 5' => [
                [ null, null, '', '     ', 'cat', 'horse', 19478 ],
                'cat'
            ],
        ];
    }
}