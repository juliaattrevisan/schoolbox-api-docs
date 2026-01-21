<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\DifferentiationProfile\Translator\ResponseModelToArray;
use Schoolbox\DifferentiationProfile\Model;

/**
 * Class differentiationProfileResponseTest
 *
 * @package Schoolbox\DifferentiationProfile\Test
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ResponseModelToArray::class)]
class DifferentiationProfileResponseTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvokeData')]
    public function testInvokeData(Model $model, array $expected): void
    {
        $translator = new ResponseModelToArray();

        $actual = $translator($model);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for `testInvokeData`.
     *
     * @return array
     */
    public static function providerInvokeData(): array
    {
        $configLoader = new \Schoolbox\Config\ArrayLoader(
            [
                'date_short' => 'M j, Y',
                'date_time' => 'g:ia',
            ]
        );

        $locale = new \Alaress_Date_Locale_Config();
        $locale->setConfig($configLoader);

        return [
            'Profile with ID and updated_at' => [
                // Test case 1: Basic profile with ID and updated_at
                new Model()
                    ->setId(1)
                    ->setUpdatedAt(\Alaress_Date_Format::newDatabaseDate('2024-01-15 14:30:00', new \DateTimeZone('UTC'), $locale)),
                [
                    'id' => 1,
                    'updated_at' => 'Jan 15, 2024 2:30pm',
                ],
            ],
            'Profile with different timestamp' => [
                // Test case 2: Profile with different date/time
                new Model()
                    ->setId(42)
                    ->setUpdatedAt(\Alaress_Date_Format::newDatabaseDate('2025-12-31 23:59:59', new \DateTimeZone('UTC'), $locale)),
                [
                    'id' => 42,
                    'updated_at' => 'Dec 31, 2025 11:59pm',
                ],
            ],
        ];
    }
}
