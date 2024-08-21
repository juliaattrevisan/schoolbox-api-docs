<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
     * @param array      $values
     * @param float|null $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestMedianData')]
    public function testMedian(array $values, ?float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Statistics::median($values), 0.0000001);
    }

    /**
     * @return array
     */
    public static function getTestMedianData(): array
    {
        return [
            'empty' => [
                [],
                null,
            ],
            'no non-numeric' => [
                [null, 'non-numeric'],
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
            'duplicate values' => [
                [4.1, 5.1, 1.1, 1.1, 3.1],
                3.1,
            ],
            'exponents' => [
                [1.3e2, 1.4e3, 3.4e-1, -1.43e1, -3.2e-3, -6.13e-4],
                0.1696935,
            ],
            'numbery strings' => [
                ['1', '12.25', '+3', '+3.25', '+03.25e1', '-06', '-06.3', '-06.5E-1'],
                2,
            ],
            'hopefully not numbery strings' => [
                ['pi', 'π', 'φ', 'e'],
                null,
            ],
            'trailing and leading spaces' => [
                // is_numeric allows leading but not trailing spaces.
                // I've chosen to allow both for consistency reasons?
                ['3 ', ' 5', ' 6.4 ', '   7', '   5.3e4 '],
                6.4,
            ],
            'key-value pairs' => [
                [
                    'key'    => 4.1,
                    'does'   => 5.1,
                    'not'    => 1.1,
                    'matter' => 3.1,
                    'kthx'   => 'non-numeric',
                    'bye'    => null,
                ],
                3.6,
            ],
        ];
    }

    /**
     * Tests calculation of box plot values.
     *
     * @param array $values
     * @param array $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getBuildBoxPlotValuesData')]
    public function testBuildBoxPlotValues(array $values, array $expected): void
    {
        $this->assertEqualsWithDelta($expected, Statistics::buildBoxPlotValues($values), 0.0000001);
    }

    /**
     * @return array
     */
    public static function getBuildBoxPlotValuesData(): array
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
            'two value' => [
                [4.1, 3.1],
                [
                    'low'    => 3.1,
                    'q1'     => 3.1,
                    'median' => 3.6,
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
                    // Junk values which must not affect result
                    "Absent",
                    '      ',
                    null,
                    'null',
                    'E',
                    'E+',
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
