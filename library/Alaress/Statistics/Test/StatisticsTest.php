<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Statistics\Test;

use Alaress\Statistics\Statistics;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Statistics
 */
class StatisticsTest extends TestCase
{
    /**
     * Tests calculation of median.
     *
     * @dataProvider getTestMedianData
     * @param array      $values
     * @param float|null $expected
     */
    public function testMedian(array $values, ?float $expected): void
    {
        $this->assertEquals($expected, Statistics::median($values));
    }

    /**
     * @return array
     */
    public function getTestMedianData(): array
    {
        return [
            'empty' => [
                [],
                null,
            ],
            'single value' => [
                [4.1],
                4.1,
            ],
            'middle value' => [
                [4.1, 5.1, 1.1],
                4.1,
            ],
            'midpoint of two values' => [
                [4.1, 5.1, 1.1, 3.1],
                3.6,
            ],
            'ignore nulls' => [
                [4.1, 5.1, 1.1, 3.1, null],
                3.6,
            ],
            'ignore non-numeric' => [
                [4.1, 5.1, 1.1, 3.1, 'non-numeric'],
                3.6,
            ],
        ];
    }

    /**
     * Tests calculation of series.
     *
     * @dataProvider getBuildSeriesMedianData
     * @param array $values
     * @param array $expected
     */
    public function testBuildSeries(array $values, array $expected): void
    {
        $this->assertEquals($expected, Statistics::buildSeries($values));
    }

    /**
     * @return array
     */
    public function getBuildSeriesMedianData(): array
    {
        return [
            'empty' => [
                [],
                [
                    'low'    => null,
                    'q1'     => null,
                    'median' => null,
                    'q3'     => null,
                    'high'   => null,
                ],
            ],
            'single value' => [
                [4.1],
                [
                    'low'    => 4.1,
                    'q1'     => 4.1,
                    'median' => 4.1,
                    'q3'     => 4.1,
                    'high'   => 4.1,
                ],
            ],
            'three value' => [
                [4.1, 5.1, 1.1],
                [
                    'low'    => 1.1,
                    'q1'     => 1.1,
                    'median' => 4.1,
                    'q3'     => 5.1,
                    'high'   => 5.1,
                ],
            ],
            'six values' => [
                [4.1, 5.1, 1.1, 3.1, 9.1, 2.1],
                [
                    'low'    => 1.1,
                    'q1'     => 2.1,
                    'median' => 3.6,
                    'q3'     => 5.1,
                    'high'   => 9.1,
                ],
            ],
            'real-world' => [
                // Real-world example from:
                // https://schoolboxdev.meriden.nsw.edu.au/modules/custom/meriden/pastoral/1915/2017/-2080-N
                [
                    "70",
                    "78",
                    "43",
                    "Absent",
                    "72",
                    "70",
                    "87",
                    "66",
                    "79",
                    "82",
                    "87",
                    "78",
                    "87",
                    "76",
                    "67",
                    "91",
                    "87",
                    "76",
                    "73",
                    "-",
                    "40",
                    "Not Assessed",
                    "-",
                    "36",
                    "48",
                    "55",
                    "64",
                    "58",
                    "57",
                    "54",
                    "67",
                    "54",
                    "39",
                    "48",
                    "70",
                    "67",
                    "51",
                    "49",
                    "60",
                    "69",
                    "69",
                    "63",
                    "64",
                    "67",
                    "64",
                    "61",
                    "52",
                    "55",
                    "49",
                    "90",
                    "87",
                    "85",
                    "85",
                    "93",
                    "90",
                    "91",
                    "96",
                    "90",
                    "94",
                    "96",
                    "91",
                    "84",
                    "94",
                    "90",
                    "90",
                    "79",
                    "91",
                    "93",
                    "75",
                    "64",
                    "61",
                    "69",
                    "60",
                    "70",
                    "49",
                    "55",
                    "79",
                    "52",
                    "61",
                    "64",
                    "88",
                    "67",
                    "60",
                    "78",
                    "N/A",
                    "78",
                    "94",
                    "72",
                    "69",
                    "87",
                    "84",
                    "75",
                    "90",
                    "87",
                    "72",
                    "75",
                    "72",
                    "63",
                    "81",
                    "81",
                    "91",
                    "81",
                    "81",
                    "94",
                    "94",
                    "94",
                    "97",
                    "96",
                    "93",
                    "88",
                    "93",
                    "94",
                    "96",
                    "97",
                    "96",
                    "87",
                    "96",
                    "90",
                    "97",
                    "91",
                    "93",
                    "85",
                ],
                [
                    'low'    => 36,
                    'q1'     => 64,
                    'median' => 78,
                    'q3'     => 90,
                    'high'   => 97,
                ]
            ],
        ];
    }
}
