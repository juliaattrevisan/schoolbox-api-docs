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
        if ($total = $this->calculateMax()) {
            return $this->calculateMark() / $total;
        }
        return 0;
    }

}
