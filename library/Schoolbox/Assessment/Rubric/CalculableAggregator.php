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
        $value = 0;

        foreach ($this as $calc) {
            $value += $calc->calculateMark();
        }

        return $value;
    }



    /**
     * The sum of all the highest descriptor values in this group
     *
     * @return float
     */
    public function calculateRowMaxSum()
    {
        $total = 0;

        foreach ($this as $calc) {
            $total += $calc->calculateRowMaxSum();
        }

        return $total;
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
        $value = 0;

        foreach ($this as $calc) {
            if ($max = $calc->calculateMax()) {
                $value = $calc->calculateScaledMark() / $max;
            }
        }

        return $value;
    }

}
