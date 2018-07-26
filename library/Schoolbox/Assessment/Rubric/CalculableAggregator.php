<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use SplObjectStorage;

class CalculableAggregator extends SplObjectStorage implements Calculable
{

    /**
     * Returns the sum of the Calculables contained herein
     *
     * @return float
     */
    public function calculateMark()
    {
        $mark = 0;

        foreach ($this as $calc) {
            $mark += $calc->calculateMark();
        }

        return $mark;
    }


    public function calculateScaledMark()
    {
        $scaledMark = $this->calculateMark();

        if ($rowMaxSum = $this->calculateRowMaxSum()) {
            $scaledMark = $this->calculateMark() * ($this->calculateMax() / $this->calculateRowMaxSum());
        }

        return $scaledMark;
    }


    /**
     * The sum of all the highest descriptor values in this group
     *
     * @return float
     */
    public function calculateRowMaxSum()
    {
        $rowMaxSum = 0;

        foreach ($this as $calc) {
            $rowMaxSum += $calc->calculateRowMaxSum();
        }

        return $rowMaxSum;
    }



    /**
     * Returns the total of the Calculables contained herein
     *
     * @return float
     */
    public function calculateMax()
    {
        $total = 0;

        foreach ($this as $calc) {
            $total += $calc->calculateMax();
        }

        return $total;
    }


    /**
     * Returns the percentage of the Calculables contained herein
     * @return float
     */
    public function calculatePercentage()
    {
        $percentage = 0;

        foreach ($this as $calc) {
            if ($max = $calc->calculateMax()) {
                $percentage = $calc->calculateScaledMark() / $max;
            }
        }

        return $percentage;
    }

}
