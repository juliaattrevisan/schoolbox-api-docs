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

namespace Schoolbox\Admin\Settings\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Config\Config;

/**
 * Validation test
 */
class ValidationTest extends TestCase
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('providerValidExample')]
    public function testValidExample($input, $expected)
    {
        $intValueSerializer = new \Schoolbox\Admin\Settings\FieldType\Number\IntValueSerializer();

        $config = $this->createMock(Config::class);
        $config->method('getOptions')->willReturn(json_encode($input['config']));

        $outcome = $intValueSerializer->serializeValue($input['value'], $config);

        /* There are other assertions that you can try (ctrl-click to follow)
         */
        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testValidExample
     *
     * @return array
     */
    public static function providerValidExample(): array
    {
        return [

            /* Add other cases that you expect to be valid
             */
            'Valid Example 1' => [
                [
                    'config' => [
                        'min' => 1,
                        'max' => 100
                    ],
                    'value' => '50'
                ],    // goes into testValidExample() as $input
                true  // goes into testValidExample() as $expected
            ],
            'Valid Example 2' => [
                [
                    'config' => [
                        'min' => 30,
                    ],
                    'value' => '600'
                ],    // goes into testValidExample() as $input
                '600'  // goes into testValidExample() as $expected
            ],

            'inValid Example 3' => [
                [
                    'config' => [
                        'min' => 30
                    ],
                    'value' => '10'
                ],
                '30'  // if outside of the range, it should return the minimum value
            ],
            'no options - pi' => [
                [
                    'config' => null,
                    'value' => '3.14'
                ],
                '3.14'  // if outside of the range, it should return the minimum value
            ],
            'min 3 - pi' => [
                [
                    'config' => [
                        'min' => 3
                    ],
                    'value' => '3.14'
                ],
                '3.14'  // if outside of the range, it should return the minimum value
            ],
        ];
    }


    /**
     * You should also test for error states that your code may raise.
     * Use the `
     * you expect your code to raise.
     *
     * @dataProvider getTestErrorRaisedData
     *
     */
    #[DataProvider('getTestErrorRaisedData')]
    public function testErrorRaised($input): void
    {
        $this->expectException(\TypeError::class);

        // Substitute this with the method call that is supposed to fail.
        $test = static function (int $b) {
            return $b + 1;
        };

        $test($input);
    }


    /**
     * Data provider for `testErrorRaised`.
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
