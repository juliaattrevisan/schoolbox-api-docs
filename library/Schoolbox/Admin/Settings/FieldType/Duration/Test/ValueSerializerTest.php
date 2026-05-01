<?php

declare(strict_types=1);

namespace Schoolbox\Admin\Settings\FieldType\Duration\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use Schoolbox\Admin\Settings\FieldType\Number\Test\IntValueSerializerTest;
use Schoolbox\Config\Config;

/**
 * Validation test
 */
class ValueSerializerTest extends IntValueSerializerTest
{
    /**
     * @param mixed $input
     * @param int   $expected
     */
    #[DataProvider('serializeDataProvider')]
    public function testIntValueSerializer($input, $expected)
    {
        $intValueSerializer = new \Schoolbox\Admin\Settings\FieldType\Duration\ValueSerializer();

        $config = $this->createMock(Config::class);
        $config->method('getOptions')->willReturn($input['config']);
        $config->method('getRequired')->willReturn($input['required'] ?? true);

        $outcome = $intValueSerializer->serializeValue($input['value'], $config);

        $this->assertEquals($expected, $outcome);
    }

    /**
     * Data provider for testIntValueSerializer
     *
     * @return array
     */
    public static function serializeDataProvider(): array
    {
        return [
            // should be able to handle all the cases from the IntValueSerializerTest
            ...parent::serializeDataProvider(),

            // Duration-specific tests for optional fields
            'optional field, empty string' => [
                [
                    'config' => '{"min":0}',
                    'value' => '',
                    'required' => false
                ],
                '0'
            ],
            'optional field, zero value' => [
                [
                    'config' => '{"min":0}',
                    'value' => '0',
                    'required' => false
                ],
                '0'
            ],
            'optional field, whitespace only' => [
                [
                    'config' => '{"min":0}',
                    'value' => '   ',
                    'required' => false
                ],
                '0'
            ],
            'optional field, valid value with min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '60',
                    'required' => false
                ],
                '60'
            ],
            'optional field, value below min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '10',
                    'required' => false
                ],
                '30'
            ],
            'optional field, value with max' => [
                [
                    'config' => '{"min":0,"max":100}',
                    'value' => '75',
                    'required' => false
                ],
                '75'
            ],
            'optional field, value exceeds max' => [
                [
                    'config' => '{"min":0,"max":100}',
                    'value' => '150',
                    'required' => false
                ],
                '100'
            ],
            'optional field, timeUnit year' => [
                [
                    'config' => '{"min":0,"max":10,"timeUnit":"year"}',
                    'value' => '5',
                    'required' => false
                ],
                '5'
            ],
            'optional field, timeUnit week' => [
                [
                    'config' => '{"min":0,"max":52,"timeUnit":"week"}',
                    'value' => '26',
                    'required' => false
                ],
                '26'
            ],
            'optional field, timeUnit day' => [
                [
                    'config' => '{"min":0,"max":365,"timeUnit":"day"}',
                    'value' => '180',
                    'required' => false
                ],
                '180'
            ],
            'optional field, timeUnit hour' => [
                [
                    'config' => '{"min":0,"max":168,"timeUnit":"hour"}',
                    'value' => '48',
                    'required' => false
                ],
                '48'
            ],
            'optional field, timeUnit minute' => [
                [
                    'config' => '{"min":0,"max":10080,"timeUnit":"minute"}',
                    'value' => '720',
                    'required' => false
                ],
                '720'
            ],

            // Duration-specific tests for required fields
            'required field, empty string falls back to min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '',
                    'required' => true
                ],
                '30'
            ],
            'required field, zero value falls back to min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '0',
                    'required' => true
                ],
                '30'
            ],
            'required field, whitespace only falls back to min' => [
                [
                    'config' => '{"min":60}',
                    'value' => '   ',
                    'required' => true
                ],
                '60'
            ],
            'required field, valid value' => [
                [
                    'config' => '{"min":30,"max":100}',
                    'value' => '50',
                    'required' => true
                ],
                '50'
            ],
            'required field, value below min' => [
                [
                    'config' => '{"min":30}',
                    'value' => '10',
                    'required' => true
                ],
                '30'
            ],
            'required field, value above max' => [
                [
                    'config' => '{"max":100}',
                    'value' => '150',
                    'required' => true
                ],
                '100'
            ],
            'required field, no config empty string' => [
                [
                    'config' => null,
                    'value' => '',
                    'required' => true
                ],
                '0'
            ],
        ];
    }
}
