<?php

/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Analytics\BoxPlot;

/**
 * Class Summary
 *
 * Calculate five point summary for boxplots
 * https://www.purplemath.com/modules/boxwhisk.htm
 *
 * @package Schoolbox\Learning\Analytics\BoxPlot
 */
class Summary
{

    /**
     * @var array
     */
    private $dataset;


    /**
     * Summary constructor.
     *
     * @param array $dataset
     */
    public function __construct(array $dataset)
    {
        $this->dataset = $this->filterDataset($dataset);
    }


    /**
     * Returns five points of quartile 1,2 and 3 and min
     * and max values for drawing box-and-whisker plot
     *
     * @return array
     */
    public function getSummary(): array
    {
        if (!empty($this->dataset)) {
            return
                [
                    'quartile1' => $this->findMedian($this->setupFirstQuartile()),
                    'quartile2' => $this->findMedian($this->dataset),
                    'quartile3' => $this->findMedian($this->setupThirdQuartile()),
                    'min'       => $this->min(),
                    'max'       => $this->max(),
                    'outliers'  => []
                ];
        }

        return [
            'quartile1' => null,
            'quartile2' => null,
            'quartile3' => null,
            'min'       => null,
            'max'       => null,
            'outliers'  => []
        ];
    }


    /**
     * @return int|float
     */
    private function min()
    {
        return $this->dataset[0];
    }


    /**
     * @return int|float
     */
    private function max()
    {
        return $this->dataset[\count($this->dataset) - 1];
    }


    /**
     * Returns five points as well as outliers.
     * New values of min, max are calculated
     * based on lower and upper thresholds
     *
     * @return array
     */
    public function getSummaryWithOutliers(): array
    {
        $fivePoints = $this->getSummary();

        if (empty($this->dataset)) {
            return $fivePoints;
        }

        $countValues = count($this->dataset);
        $allOutliers = [];
        $minimum = $this->min();
        $maximum = $this->max();

        /* Lower whisker
         * Use the first value that is above the lowerThreshold
         */
        $lowerThreshold = $fivePoints['quartile1'] - (1.5 * $this->generateInterquartileRange());

        for ($i = 0; $i < $countValues; $i++) {
            if ($this->dataset[$i] > $lowerThreshold) {
                $minimum = $this->dataset[$i];
                break;
            }
        }

        if ($minimum !== null) {
            $minimum = round($minimum, 1);
        }

        /* Upper whisker
         * Use the first value that is below the upperThreshold
         */
        $upperThreshold = $fivePoints['quartile3'] + (1.5 * $this->generateInterquartileRange());

        for ($i = $countValues - 1; $i >= 0; $i--) {
            if ($this->dataset[$i] < $upperThreshold) {
                $maximum = $this->dataset[$i];
                break;
            }
        }

        if ($maximum !== null) {
            $maximum = round($maximum, 1);
        }

        /* Outlier stack
         * Stack with information about the outlier,
         * not just its value
         */
        for ($i = 0; $i < $countValues; $i++) {
            if (($this->dataset[$i] < $lowerThreshold) || ($this->dataset[$i] > $upperThreshold)) {
                $allOutliers[] = $this->dataset[$i];
            }
        }

        return
            [
                'quartile1' => $fivePoints['quartile1'],
                'quartile2' => $fivePoints['quartile2'],
                'quartile3' => $fivePoints['quartile3'],
                'min'       => $minimum,
                'max'       => $maximum,
                'outliers'  => $allOutliers,
            ];
    }


    /**
     * Find the values used to calculate first quartile.
     *
     * @return array
     */
    private function setupFirstQuartile(): array
    {
        if (count($this->dataset) === 1) {
            return $this->dataset;
        }
        return array_slice($this->dataset, 0, (int)floor(count($this->dataset) / 2));
    }


    /**
     * Find the values used to calculate third quartile.
     *
     * @return array
     */
    private function setupThirdQuartile(): array
    {
        $countValues = count($this->dataset);

        if ($countValues === 1) {
            return $this->dataset;
        }

        $even = !($countValues % 2);

        if ($even) {
            $secondHalf = array_slice($this->dataset, (int)floor($countValues / 2), $countValues);
        } else {
            $secondHalf = array_slice($this->dataset, (int)ceil($countValues / 2), $countValues);
        }

        return $secondHalf;
    }


    /**
     * Calculates medians
     *
     * @param array $dataset
     * @return float
     */
    private function findMedian(array $dataset): float
    {
        $countValues = count($dataset);

        if ($countValues === 1) {
            return $dataset[0];
        }

        /* Figure out medians in different ways depending
         * on even or odd number of elements
         */
        $halfway = (int)floor($countValues / 2);

        $even = !($countValues % 2);

        /* Careful of these offsets */
        if ($even) {
            $median = ($dataset[$halfway - 1] + $dataset[$halfway]) / 2.0;
        } else {
            $median = $dataset[$halfway];
        }

        return (float)$median;
    }


    /**
     * Only care about numeric and non-null values
     *
     * @param array $dataset
     * @return array
     */
    private function filterDataset(array $dataset): array
    {
        if (empty($dataset)) {
            return $dataset;
        }

        // Exclude null values
        $values = array_values(array_filter($dataset, static function ($var) {
            return ((is_int($var) || is_float($var)) && $var >= 0);
        }));

        sort($values);

        return $values;
    }


    /**
     * Generates Interquartile range
     *
     * @return float|null
     */
    private function generateInterquartileRange(): ?float
    {
        return ($this->findMedian($this->setupThirdQuartile()) - $this->findMedian($this->setupFirstQuartile()));
    }
}
