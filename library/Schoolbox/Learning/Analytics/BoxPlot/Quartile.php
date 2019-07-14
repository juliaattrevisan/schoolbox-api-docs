<?php

/**
 * https://www.purplemath.com/modules/boxwhisk.htm
 * Class Quartile
 */

namespace Schoolbox\Learning\Analytics\BoxPlot;

class Quartile
{

    /**
     * Find the values used to calculate first quartile.
     *
     * @param array $dataset
     * @return array
     */
    public function setupFirstQuartile(array $dataset)
    {
        if (!isset($dataset) || !is_array($dataset) || empty($dataset) || !array_filter($dataset)) {
            return [];
        }

        // Exclude null values
        $values = array_values(array_filter($dataset, static function ($var) {
            return (is_int($var) || is_float($var));
        }));
        sort($values);

        if (count($values) === 1) {
            return $values;
        }
        return array_slice($values, 0, floor(count($values) / 2));
    }

    /**
     * Find the values used to calculate third quartile.
     *
     * @param array $dataset
     * @return array
     */
    public function setupThirdQuartile(array $dataset)
    {
        if (!isset($dataset) || !is_array($dataset) || empty($dataset) || !array_filter($dataset)) {
            return [];
        }

        // Exclude null values
        $values = array_values(array_filter($dataset, static function ($var) {
            return (is_int($var) || is_float($var));
        }));
        sort($values);

        if (count($values) === 1) {
            return $values;
        }
        $even = !(count($values) % 2);

        if ($even) {
            $secondHalve = array_slice($values, floor(count($values) / 2), count($values));
        } else {
            $secondHalve = array_slice($values, ceil(count($values) / 2), count($values));
        }
        return $secondHalve;
    }

    /**
     * Calculates medians
     *
     * @param array $dataset
     * @return float|null
     */
    public function findMedian(array $dataset)
    {
        if (!isset($dataset) || !is_array($dataset) || empty($dataset) || !array_filter($dataset)) {
            return null;
        }

        // Exclude null marks
        $values = array_values(array_filter($dataset, static function ($var) {
            return (is_int($var) || is_float($var));
        }));
        sort($values);

        if (empty($values)) {
            return null;
        } elseif (count($values) === 1) {
            return $values[0];
        }

        $median = null;
        /* Figure out medians in different ways depending
         * on even or odd number of elements
         */
        $halfway = floor(count($values) / 2);

        $even = !(count($values) % 2);

        /* Careful of these offsets */
        if ($even) {
            $median = ($values[$halfway - 1] + $values[$halfway]) / 2.0;
        } else {
            $median = $values[$halfway];
        }

        return (float)$median;
    }
}