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
        return static::internalMedian(static::preProcess($values));
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

        // Only one value? It must be all of the return values
        $count = \count($values);
        if ($count === 1) {
            $value = (float)array_pop($values);
            return [
                'low'    => $value,
                'q1'     => $value,
                'median' => $value,
                'q3'     => $value,
                'high'   => $value,
            ];
        }

        $series = [
            'low'    => (float)min($values),
            'high'   => (float)max($values),
            'median' => static::internalMedian($values),
        ];

        // Calculate q1 (median of lower half), q3 (median of upper half)
        $sliceSize  = (int)max($count / 2, 1);
        $lowValues  = \array_slice($values, 0, $sliceSize);
        $highValues = \array_slice($values, -$sliceSize);
        $series['q1'] = static::internalMedian($lowValues);
        $series['q3'] = static::internalMedian($highValues);
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
        $values = array_filter(array_map('trim', $values), 'is_numeric');
        sort($values);
        return $values;
    }


    /**
     * Internal median method.
     *
     * Not for external consumption, as it assumes that `$values` has already
     * been passed through `preProcess`.
     *
     * @param array $values
     * @return float|null
     */
    private static function internalMedian(array $values): ?float
    {
        // This _could_ be abstracted to a percentile function, but percentiles
        // are a surprisingly deep rabbit-hole:
        // https://en.wikipedia.org/wiki/Percentile#The_nearest-rank_method
        // https://en.wikipedia.org/wiki/Percentile#The_linear_interpolation_between_closest_ranks_method

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
}
