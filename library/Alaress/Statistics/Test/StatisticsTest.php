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
        ];
    }
}
