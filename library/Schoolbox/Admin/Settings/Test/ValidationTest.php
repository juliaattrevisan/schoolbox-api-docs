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
        $config->method('getOptions')->willReturn($input['config']);

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
                    'config' => '{"min": 1,"max": 100}',
                    'value' => '50'
                ],    // goes into testValidExample() as $input
                true  // goes into testValidExample() as $expected
            ],
            'Valid Example 2' => [
                [
                    'config' => '{"min":30}',
                    'value' => '600'
                ],    // goes into testValidExample() as $input
                '600'  // goes into testValidExample() as $expected
            ],

            'inValid Example 3' => [
                [
                    'config' => '{"min":30}',
                    'value' => '10'
                ],
                '30'  // if outside of the range, it should return the minimum value
            ],
            'no options - pi' => [
                [
                    'config' => null,
                    'value' => '3.14'
                ],
                '3'  // if outside of the range, it should return the minimum value
            ],
            'min 3 - pi' => [
                [
                    'config' => '{"min":3}',
                    'value' => '3.14'
                ],
                '3'  // if outside of the range, it should return the minimum value
            ],
            'bigger than' => [
                [
                    'config' => '{"max":100}',
                    'value' => '101'
                ],    // goes into testValidExample() as $input
                '100'  // if outside of the range, it should return the maximum value
            ],
            'empty config 1' => [
                [
                    'config' => '',
                    'value' => '1'
                ],
                '1'
            ],
            'empty config -100' => [
                [
                    'config' => '',
                    'value' => '-100'
                ],
                '-100'
            ],
            '0val 30 min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '0'
                ],
                '30' // this was the cause of this issue, lets check and ensure it works
            ],
            'null config, 100 val' => [
                [
                    'config' => null,
                    'value' => '100'
                ],
                '100' // if no config, just return the value
            ],
            '35 head whitespace val, 30 min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '   35'
                ],
                '35'
            ],
            '60tail whitespace val, 30 min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '60            '
                ],
                '60'
            ],
            'min and val 30' => [ // similar check to notif blackout, ensure the setting something that is min, works...
                [
                    'config' => '{"min":30}',
                    'value' => '30'
                ],
                '30'
            ],
            'max and val 100' => [ // similar check to notif blackout, ensure the setting something that is max, works...
                [
                    'config' => '{"max":100}',
                    'value' => '100'
                ],
                '100'
            ],
            'weird number string value' => [
                [
                    'config' => '{"min":30}',
                    'value' => '1,000.00'
                ],
                '30' // comma causes issue, so should return min
            ],
            '1e3 value' => [ // even though the frontend validator fails, if you click the up arrow it will set the value to int and convert the value to 1000
                [
                    'config' => '{"min":30}',
                    'value' => '1e3'
                ],
                '1000'
            ],
            'null value' => [ // if the value is null, it should return the minimum value
                [
                    'config' => '{"min":30}',
                    'value' => null
                ],
                '30'
            ],
        ];
    }
}
