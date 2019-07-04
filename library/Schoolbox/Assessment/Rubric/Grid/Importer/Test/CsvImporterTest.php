<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporter\Test;

use Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporter;
use PHPUnit\Framework\TestCase;

/**
 * Test for the CSV importer.
 *
 * @covers \Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporter
 */
class CsvImporterTest extends TestCase
{
    /**
     * Data provider for testIterateIndicators.
     *
     * @return iterable
     */
    public function getTestIterateIndicatorsData(): iterable
    {
        // Set up test data: only the square passed in really matters here
        $test1 = new CsvImporter(
            [1, 2, 3,],
            [
                ['name' => 'Cap 1'],
                ['name' => 'Cap 2'],
            ],
            [
                [
                    '', '', 1, 2, 3,
                ],
                [
                    'Cap 2',
                    'Ind 1',
                    '',
                    '',
                    '',
                ],
                [
                    '',
                    '',
                    '',
                    '',
                    '',
                ],
                [
                    'Cap 2',
                    'Ind 2',
                    '',
                    '',
                    '',
                ],
                [
                    '',
                    '',
                    '',
                    '',
                    '',
                ],
            ],
            false,
            false
        );

        yield 'capability not in use' => [$test1, 'Cap 1', []];
        yield 'indicators not in use' => [$test1, 'Cap 2', []];


        $test2 = new CsvImporter(
            [1, 2, 3,],
            [
                ['name' => 'Cap 1'],
                ['name' => 'Cap 2'],
            ],
            [
                [
                    '', '', 1, 2, 3,
                ],
                [
                    'Cap 1',
                    'Ind 1',
                    'Desc 1',
                    'Desc 2',
                    'Desc 3',
                ],
                [
                    '',
                    '',
                    '',
                    '',
                    '',
                ],
                [
                    'Cap 2',
                    'Ind 1',
                    'Desc 1',
                    'Desc 2',
                    'Desc 3',
                ],
                [
                    '',
                    '',
                    '6',
                    '7',
                    '8',
                ],
                [
                    'Cap 2',
                    'Ind 2',
                    'Desc 1',
                    '',
                    'Desc 3',
                ],
                [
                    '',
                    '',
                    '6',
                    '',
                    '8',
                ],
                [
                    'Cap 2',
                    'Ind 3',
                    '',
                    'Desc 2',
                    '',
                ],
                [
                    '',
                    '',
                    '6',
                    '7',
                    '',
                ],
            ],
            false,
            false
        );

        yield 'capability with one indicator' => [
            $test2,
            'Cap 1',
            [
                [
                    'name'        => 'Ind 1',
                    'descriptors' => [
                        ['name' => 'Desc 1', 'value' => null,],
                        ['name' => 'Desc 2', 'value' => null,],
                        ['name' => 'Desc 3', 'value' => null,],
                    ],
                ],
            ]
        ];
        yield 'capability with multiple indicators' => [
            $test2,
            'Cap 2',
            [
                [
                    'name'        => 'Ind 1',
                    'descriptors' => [
                        ['name' => 'Desc 1', 'value' => 6,],
                        ['name' => 'Desc 2', 'value' => 7,],
                        ['name' => 'Desc 3', 'value' => 8,],
                    ],
                ],
                [
                    'name'        => 'Ind 2',
                    'descriptors' => [
                        ['name' => 'Desc 1', 'value' => 6,],
                        null,
                        ['name' => 'Desc 3', 'value' => 8,],
                    ],
                ],
                [
                    'name'        => 'Ind 3',
                    'descriptors' => [
                        ['name' => '',       'value' => 6,],
                        ['name' => 'Desc 2', 'value' => 7,],
                        null,
                    ],
                ],
            ]
        ];
    }


    /**
     * @dataProvider getTestIterateIndicatorsData
     * @covers \Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporter::iterateIndicators
     *
     * @param CsvImporter $importer
     * @param string      $capability
     * @param array       $expectedIndicators
     */
    public function testIterateIndicators(CsvImporter $importer, string $capability, array $expectedIndicators): void
    {
        $this->assertSame($expectedIndicators, $importer->iterateIndicators($capability));
    }
}
