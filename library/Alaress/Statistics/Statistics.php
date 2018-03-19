<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Statistics;

/**
 * Class for basic statistics generation.
 *
 * Currently includes calculations for:
 * * median
 * * series
 */
class Statistics
{
    /**
     * Determines the median value of $values.
     *
     * @param array $values
     * @return float|null
     */
    public static function median(array $values): ?float
    {
        // This _could_ be abstracted to a percentile function, but percentiles
        // are a surprisingly deep rabbit-hole:
        // https://en.wikipedia.org/wiki/Percentile#The_nearest-rank_method
        // https://en.wikipedia.org/wiki/Percentile#The_linear_interpolation_between_closest_ranks_method

        $values = static::preProcess($values);

        // Can't median a value which doesn't exist
        if (empty($values)) {
            return null;
        }

        // Only one value? it must be the median
        $count = \count($values);
        if ($count === 1) {
            return (float)array_pop($values);
        }

        // Median: midpoint value
        // If exactly divisible into two, use midpoint of highest min and lowest max
        sort($values);
        $index = ($count-1) / 2;
        if (\is_int($index)) {
            return (float)$values[$index];
        }

        $lowValue  = $values[(int)floor($index)];
        $highValue = $values[(int)ceil($index)];

        return (float)(($highValue + $lowValue) / 2);
    }


    /**
     * Builds low/high/median/IQR for the list of values.
     *
     * @param array $values
     * @return array low:    min value
     *               q1:     median of lowest half of values
     *               median: median value
     *               q1:     median of highest half of values
     *               high:   max value
     */
    public static function buildBoxPlotValues(array $values): array
    {
        $values = static::preProcess($values);

        // Empty case: all null
        if (empty($values)) {
            return [
                'low'    => null,
                'q1'     => null,
                'median' => null,
                'q3'     => null,
                'high'   => null,
            ];
        }

        $series = [
            'low'    => min($values),
            'high'   => max($values),
            'median' => static::median($values),
        ];

        // Calculate q1 (median of lower half), q3 (median of upper half)
        $sliceSize  = (int)max(\count($values) / 2, 1);
        $lowValues  = \array_slice($values, 0, $sliceSize);
        $highValues = \array_slice($values, -$sliceSize);
        $series['q1'] = static::median($lowValues);
        $series['q3'] = static::median($highValues);
        return $series;
    }


    /**
     * Pre-process the set of values for use in median and series calculations.
     * * Remove non-numeric values
     * * Sort in ascending order
     *
     * @param array $values
     * @return array
     */
    private static function preProcess(array $values): array
    {
        $values = array_filter($values, 'is_numeric');
        sort($values);
        return $values;
    }
}
