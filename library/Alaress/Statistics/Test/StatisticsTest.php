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
    public function testMedian(array $values, ?float $expected)
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
     * @param array $values
     * @param array $expected
     */
    public function testBuildSeries(array $values, array $expected)
    {
        $this->assertEquals($expected, Statistics::buildSeries($values));
    }
}
