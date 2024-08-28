<?php

/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Analytics\BoxPlot\Test\Unit;

use PHPUnit\Framework\TestCase;
use Schoolbox\Learning\Analytics\BoxPlot\Summary;

/**
 * Tests for Summary methods
 */
class SummaryTest extends TestCase
{
    /**
     * Data provider for `getSummary`.
     *
     * @return array
     */
    public static function providerForGetSummary()
    {
        return [
            [
                'marks'    => [null],
                'expected' => [
                    'quartile1' => null,
                    'quartile2' => null,
                    'quartile3' => null,
                    'min'       => null,
                    'max'       => null,
                    'outliers'  => []
                ]
            ],
            [
                'marks'    => [null, null, null, 'null', 'sadf', '234', '23.234', -22, '-54'],
                'expected' => [
                    'quartile1' => null,
                    'quartile2' => null,
                    'quartile3' => null,
                    'min'       => null,
                    'max'       => null,
                    'outliers'  => []
                ]
            ],
            [
                'marks'    => [null, 'null', 1.3, 2, 3, 4, 5, '234', null, 0, '1e3'],
                'expected' => [
                    'quartile1' => 1.3,
                    'quartile2' => 2.5,
                    'quartile3' => 4,
                    'min'       => 0,
                    'max'       => 5,
                    'outliers'  => []
                ]
            ],
            [
                'marks'    => [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100],
                'expected' => [
                    'quartile1' => 4,
                    'quartile2' => 85,
                    'quartile3' => 93,
                    'min'       => 1,
                    'max'       => 100,
                    'outliers'  => []
                ]

            ],
            [
                'marks'    => [1, 1, 2, 2, 3, 3, 4, 4, 5, 5],
                'expected' => [
                    'quartile1' => 2,
                    'quartile2' => 3,
                    'quartile3' => 4,
                    'min'       => 1,
                    'max'       => 5,
                    'outliers'  => []
                ]
            ],
            [
                'marks'     => [3.4, 4.3, 6.9, 7.5],
                'expected' => [
                    'quartile1' => 3.85,
                    'quartile2' => 5.6,
                    'quartile3' => 7.2,
                    'min'       => 3.4,
                    'max'       => 7.5,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [7.3, 7.3, 7.3, 7.3, 7.3],
                'expected' => [
                    'quartile1' => 7.3,
                    'quartile2' => 7.3,
                    'quartile3' => 7.3,
                    'min' => 7.3,
                    'max' => 7.3,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [65, 85],
                'expected' => [
                    'quartile1' => 65,
                    'quartile2' => 75,
                    'quartile3' => 85,
                    'min' => 65,
                    'max' => 85,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [0, 0, 3, 50, 53, 61, 80, 83],
                'expected' => [
                    'quartile1' => 1.5,
                    'quartile2' => 51.5,
                    'quartile3' => 70.5,
                    'min' => 0,
                    'max' => 83,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [3.343, 5.2, 0, 0, null, null, 1000, 3, 50, 53, 61, 80, 83],
                'expected' => [
                    'quartile1' => 3,
                    'quartile2' => 50,
                    'quartile3' => 80,
                    'min' => 0,
                    'max' => 1000,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [9.3],
                'expected' => [
                    'quartile1' => 9.3,
                    'quartile2' => 9.3,
                    'quartile3' => 9.3,
                    'min' => 9.3,
                    'max' => 9.3,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [10.2, 14.1, 14.4, 14.4, 14.4, 14.5, 14.5, 14.6, 14.7, 14.7, 14.7, 14.9, 15.1, 15.9, 16.4],
                'expected' => [
                    'quartile1' => 14.4,
                    'quartile2' => 14.6,
                    'quartile3' => 14.9,
                    'min' => 10.2,
                    'max' => 16.4,
                    'outliers'  => []
                ]
            ],
            [
                'marks' => [21, 23, 24, 25, 29, 33, 49],
                'expected' => [
                    'quartile1' => 23,
                    'quartile2' => 25,
                    'quartile3' => 33,
                    'min' => 21,
                    'max' => 49,
                    'outliers'  => []
                ]
            ],
        ];
    }

    /**
     *
     *
     * @param array $marks
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForGetSummary')]
    public function testGetSummary($marks, $expected)
    {
        $summary = new Summary($marks);
        $statistics = $summary->getSummary();

        $this->assertEqualsWithDelta($statistics, $expected, 0.000000001);
    }

    /**
     * Data provider for `getSummaryWithOutliers`.
     *
     * @return array
     */
    public static function providerForGetSummaryWithOutliers()
    {
        return [
            [
                'marks' => [],
                'expected' => [
                    'quartile1' => null,
                    'quartile2' => null,
                    'quartile3' => null,
                    'min' => null,
                    'max' => null,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [null, null, null, 'null', 'sadf', '234', '23.234', -22, '-54'],
                'expected' => [
                    'quartile1' => null,
                    'quartile2' => null,
                    'quartile3' => null,
                    'min' => null,
                    'max' => null,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [null, 'null', 1.3, 2, 3, 4, 5, '234', null, 0, '1e3'],
                'expected' => [
                    'quartile1' => 1.3,
                    'quartile2' => 2.5,
                    'quartile3' => 4,
                    'min' => 0,
                    'max' => 5,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [1, 2, 3, 4, 5, 60, 70, 85, 91, 91, 92, 93, 94, 95, 100],
                'expected' => [
                    'quartile1' => 4,
                    'quartile2' => 85,
                    'quartile3' => 93,
                    'min' => 1,
                    'max' => 100,
                    'outliers' => [],
                ]

            ],
            [
                'marks' => [7.3, 7.3, 7.3, 7.3, 7.3],
                'expected' => [
                    'quartile1' => 7.3,
                    'quartile2' => 7.3,
                    'quartile3' => 7.3,
                    'min' => 7.3,
                    'max' => 7.3,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [65, 85],
                'expected' => [
                    'quartile1' => 65,
                    'quartile2' => 75,
                    'quartile3' => 85,
                    'min' => 65,
                    'max' => 85,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [0, 0, 3, 50, 53, 61, 80, 83],
                'expected' => [
                    'quartile1' => 1.5,
                    'quartile2' => 51.5,
                    'quartile3' => 70.5,
                    'min' => 0,
                    'max' => 83,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [3.343, 5.2, 0, 0, null, null, 1000, 3, 50, 53, 61, 80, 83],
                'expected' => [
                    'quartile1' => 3,
                    'quartile2' => 50,
                    'quartile3' => 80,
                    'min' => 0,
                    'max' => 83,
                    'outliers' => [1000],
                ]
            ],
            [
                'marks' => [9.3],
                'expected' => [
                    'quartile1' => 9.3,
                    'quartile2' => 9.3,
                    'quartile3' => 9.3,
                    'min' => 9.3,
                    'max' => 9.3,
                    'outliers' => [],
                ]
            ],
            [
                'marks' => [10.2, 14.1, 14.4, 14.4, 14.4, 14.5, 14.5, 14.6, 14.7, 14.7, 14.7, 14.9, 15.1, 15.9, 16.4],
                'expected' => [
                    'quartile1' => 14.4,
                    'quartile2' => 14.6,
                    'quartile3' => 14.9,
                    'min' => 14.1,
                    'max' => 15.1,
                    'outliers' => [10.2, 15.9, 16.4],
                ]
            ],
            [
                'marks' => [21, 23, 24, 25, 29, 33, 49],
                'expected' => [
                    'quartile1' => 23,
                    'quartile2' => 25,
                    'quartile3' => 33,
                    'min' => 21,
                    'max' => 33,
                    'outliers' => [49],
                ]
            ],
            [
                'marks' => [7, 8, 45, 46, 47, 47, 48, 48, 48, 99],
                'expected' => [
                    'quartile1' => 45,
                    'quartile2' => 47,
                    'quartile3' => 48,
                    'min' => 45,
                    'max' => 48,
                    'outliers' => [7, 8, 99],
                ]
            ],
        ];
    }

    /**
     *
     *
     * @param array $marks
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerForGetSummaryWithOutliers')]
    public function testGetSummaryWithOutliers($marks, $expected)
    {
        $summary = new Summary($marks);
        $statistics = $summary->getSummaryWithOutliers();

        $this->assertEquals($statistics, $expected);
    }
}
