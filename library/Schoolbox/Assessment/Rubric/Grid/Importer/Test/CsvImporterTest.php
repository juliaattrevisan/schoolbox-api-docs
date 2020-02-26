<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Importer\Test;

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
     * Data provider for testRetrieveCapabilitiesWithIndicators.
     *
     * @return iterable
     */
    public function getTestRetrieveCapabilitiesWithIndicatorsData(): iterable
    {
        // Set up test data: only the square passed in really matters here
        $test1 = new CsvImporter(
            [1, 2, 3,],
            [
                [
                    'name'     => 'Cap 1',
                    'maxValue' => null,
                ],
                [
                    'name'     => 'Cap 2',
                    'maxValue' => null,
                ],
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

        yield 'capabilities not in use' => [
            $test1,
            [
                [
                    'name'       => 'Cap 1',
                    'maxValue'   => null,
                    'indicators' => [],
                ],
                [
                    'name'       => 'Cap 2',
                    'maxValue'   => null,
                    'indicators' => [],
                ],
            ],
        ];


        $test2 = new CsvImporter(
            [1, 2, 3,],
            [
                [
                    'name'     => 'Cap 1',
                    'maxValue' => null,
                ],
                [
                    'name'     => 'Cap 2',
                    'maxValue' => null,
                ],
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

        yield 'capability with indicators' => [
            $test2,
            [

                [
                    'name'       => 'Cap 1',
                    'maxValue'   => null,
                    'indicators' => [
                        [
                            'name'        => 'Ind 1',
                            'descriptors' => [
                                ['name' => 'Desc 1', 'value' => null,],
                                ['name' => 'Desc 2', 'value' => null,],
                                ['name' => 'Desc 3', 'value' => null,],
                            ],
                        ],
                    ],
                ],
                [
                    'name'       => 'Cap 2',
                    'maxValue'   => null,
                    'indicators' => [
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
                    ],
                ],
            ]
        ];
    }


    /**
     * @dataProvider getTestRetrieveCapabilitiesWithIndicatorsData
     * @covers \Schoolbox\Assessment\Rubric\Grid\Importer\CsvImporter::retrieveCapabilitiesWithIndicators
     *
     * @param CsvImporter $importer
     * @param array       $expectedIndicators
     */
    public function testRetrieveCapabilitiesWithIndicators(
        CsvImporter $importer,
        array $expectedIndicators
    ): void {
        $this->assertSame($expectedIndicators, $importer->retrieveCapabilitiesWithIndicators());
    }
}
