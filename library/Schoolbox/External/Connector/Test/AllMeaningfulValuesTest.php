<?php

/**
 * AllMeaningfulValueTest
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
use Schoolbox\External\Connector\AllMeaningfulValues;

/**
 * Class AllMeaningfulValuesTest
 *
 * From an array of values, find all meaningful values
 * The value must not be any of the following possibilities:
 *
 * null
 * an empty string
 * consist only of spaces
 *
 * If the array of values contains no meaningful values,
 * default to an empty array
 *
 * @package Schoolbox\External\Connector\Test
 */
class AllMeaningfulValuesTest extends TestCase
{
    /**
     * @param array  $input
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerValidMeaningfulValue')]
    public function testValidMeaningfulValue(array $input, array $expected)
    {
        $this->assertEquals($expected, AllMeaningfulValues::find($input));
    }


    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function providerValidMeaningfulValue(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'Valid Example 1' => [
                [],
                []
            ],

            'Valid Example 2' => [
                [ null, null, '', '     ' ],
                []
            ],

            'Valid Example 3' => [
                [ null, null, '', '     ', 0 ],
                [ '0' ]
            ],

            'Valid Example 4' => [
                [ null, null, '', '     ', 0, 'cat', 'horse', '0' ],
                [ '0', 'cat', 'horse', '0' ]
            ],

            'Valid Example 5' => [
                [ null, null, '', '     ', 'cat', 'horse', 19478 ],
                [ 'cat', 'horse', '19478' ]
            ],

            'Valid Example 6' => [
                [ 'cat', 'blue russian cat', 'horse', 19478 ],
                [ 'cat', 'blue russian cat', 'horse', '19478' ]
            ],
        ];
    }
}
