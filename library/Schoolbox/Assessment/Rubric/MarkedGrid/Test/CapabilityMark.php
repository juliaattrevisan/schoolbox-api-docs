<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;

/**
 * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark
 */
class CapabilityMark extends TestCase
{
    /**
     * Data provider for `testCalculateScaledMark`.
     *
     * @return iterable
     */
    public function getTestCalculateScaledMark(): iterable
    {
        $generator = new Generator();

        $capabilities = [
            1 => [
                'name'       => 'Capability 1',
                'indicators' => [
                    11 => ['name' => 'Indicator 1.1'],
                    12 => ['name' => 'Indicator 1.2'],
                    13 => ['name' => 'Indicator 1.3'],
                ],
            ],
            2 => [
                'name'       => 'Capability 2',
                'indicators' => [
                    21 => ['name' => 'Indicator 2.1'],
                    22 => ['name' => 'Indicator 2.2'],
                    23 => ['name' => 'Indicator 2.3'],
                ],
            ],
        ];
        $levels = [
            1 => ['name' => 'Poor',],
            2 => ['name' => 'Okay',],
            3 => ['name' => 'Good',],
            4 => ['name' => 'Great',],
            5 => ['name' => 'Excellent',],
        ];
        $capabilityUsages = [
            1 => [
                'indicators' => [
                    11 => [
                        'descriptors' => [
                            111 => [
                                'levelId'     => 1,
                                'description' => 'Poor',
                                'value'       => 0,
                            ],
                            112 => [
                                'levelId'     => 2,
                                'description' => 'Okay',
                                'value'       => 2,
                            ],
                            114 => [
                                'levelId'     => 4,
                                'description' => 'Great',
                                'value'       => 6,
                            ],
                            115 => [
                                'levelId'     => 5,
                                'description' => 'Excellent',
                                'value'       => 8,
                            ],
                        ],
                    ],
                    12 => [
                        'descriptors' => [
                            121 => [
                                'levelId'     => 1,
                                'description' => 'Poor',
                                'value'       => 0,
                            ],
                            122 => [
                                'levelId'     => 2,
                                'description' => 'Okay',
                                'value'       => 2,
                            ],
                            123 => [
                                'levelId'     => 3,
                                'description' => 'Good',
                                'value'       => 4,
                            ],
                            125 => [
                                'levelId'     => 5,
                                'description' => 'Excellent',
                                'value'       => 8,
                            ],
                        ],
                    ],
                ],
            ],
            2 => [
                'indicators' => [
                    22 => [
                        'descriptors' => [
                            222 => [
                                'levelId'     => 2,
                                'description' => 'Okay',
                                'value'       => 2,
                            ],
                            223 => [
                                'levelId'     => 3,
                                'description' => 'Good',
                                'value'       => 4,
                            ],
                            224 => [
                                'levelId'     => 4,
                                'description' => 'Great',
                                'value'       => 6,
                            ],
                            225 => [
                                'levelId'     => 5,
                                'description' => 'Excellent',
                                'value'       => 8,
                            ],
                        ],
                    ],
                    23 => [
                        'descriptors' => [
                            231 => [
                                'levelId'     => 1,
                                'description' => 'Poor',
                                'value'       => 0,
                            ],
                            233 => [
                                'levelId'     => 3,
                                'description' => 'Good',
                                'value'       => 4,
                            ],
                            234 => [
                                'levelId'     => 4,
                                'description' => 'Great',
                                'value'       => 6,
                            ],
                            235 => [
                                'levelId'     => 4,
                                'description' => 'Excellent',
                                'value'       => 8,
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $capabilityMarks = [
            1 => [
                'indicators' => [
                    11 => [
                        // inferred value: 0
                        'descriptorId' => 111,
                    ],
                    12 => [
                        // inferred value: 8
                        'descriptorId' => 125,
                    ],
                ],
            ],
            2 => [
                'indicators' => [
                    22 => [
                        // inferred value: 6
                        'descriptorId' => 224,
                    ],
                    23 => [
                        // inferred value: 0
                    ],
                ],
            ],
        ];


        // No scaling on cap marks for this grid
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (unscaled)' => [$markedGrid->getCapabilityMarks()[1], 8];
        yield 'Capability mark 2 (unscaled)' => [$markedGrid->getCapabilityMarks()[2], 6];

        // The same grid, with scaling factors applied
        $scaledCapabilityUsages = $capabilityUsages;
        $scaledCapabilityUsages[1]['maxValue'] = 8;
        $scaledCapabilityUsages[2]['maxValue'] = 32;
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $scaledCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (scaled 0.5x)' => [$markedGrid->getCapabilityMarks()[1], 4];
        yield 'Capability mark 2 (scaled 2.0x)' => [$markedGrid->getCapabilityMarks()[2], 12];
    }


    /**
     * @dataProvider getTestCalculateScaledMark
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::calculateScaledMark
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param float                           $expected
     */
    public function testCalculateScaledMark(RubricMarkedGrid\CapabilityMark $capabilityMark, float $expected): void
    {
        $this->assertSame($expected, $capabilityMark->calculateScaledMark());
    }


    // Testable methods:
    // calculateScaledMark
    // calculateIndicatorMarkSum
    // retrieveNormalizedMark
    // retrieveHumanReadableMark
    // iterateIndicatorUsages
    // retrieveIndicatorMark
}
